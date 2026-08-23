<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskReminderNotification;
use App\Services\ApprovalWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendTaskReminders extends Command
{
    public function __construct(private readonly ApprovalWorkflow $approvalWorkflow)
    {
        parent::__construct();
    }

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía recordatorios de vencimiento y revisión sin duplicados';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sent = 0;
        Organization::where('is_active', true)->each(function (Organization $organization) use (&$sent): void {
            if ($organization->reminders_enabled) {
                $sent += $this->sendDueReminders($organization);
            }
            if ($organization->review_alerts_enabled) {
                $sent += $this->sendReviewAlerts($organization);
            }
        });
        $this->info("Recordatorios enviados: {$sent}");

        return self::SUCCESS;
    }

    private function sendDueReminders(Organization $organization): int
    {
        $sent = 0;
        $today = today();
        $days = collect($organization->reminder_days ?: [7, 3, 1])->map(fn ($day) => (int) $day);
        Task::withoutGlobalScopes()->where('organization_id', $organization->id)
            ->whereNotIn('status', ['completed', 'cancelled'])->whereNotNull('due_at')
            ->with(['assignees' => fn ($query) => $query->withoutGlobalScopes(), 'assignee' => fn ($query) => $query->withoutGlobalScopes()])
            ->chunkById(100, function ($tasks) use ($organization, $today, $days, &$sent): void {
                foreach ($tasks as $task) {
                    $dueDate = $task->due_at->startOfDay();
                    $remaining = $today->diffInDays($dueDate, false);
                    if (! $days->contains($remaining) && ! ($organization->overdue_alerts_enabled && $remaining === -1)) {
                        continue;
                    }
                    $type = $remaining === -1 ? 'task_overdue' : "task_due_{$remaining}";
                    $title = $remaining === -1 ? 'Acción vencida' : "Acción próxima a vencer: {$remaining} día".($remaining === 1 ? '' : 's');
                    $message = $remaining === -1
                        ? "La acción '{$task->title}' venció el {$dueDate->format('d/m/Y')}."
                        : "La acción '{$task->title}' vence el {$dueDate->format('d/m/Y')}.";
                    foreach ($this->responsibles($task) as $user) {
                        $sent += $this->notifyOnce($organization, $task, $user, $type, $dueDate->toDateString(), $title, $message);
                    }
                }
            });

        return $sent;
    }

    private function sendReviewAlerts(Organization $organization): int
    {
        $sent = 0;
        Task::withoutGlobalScopes()->where('organization_id', $organization->id)->where('status', 'in_review')
            ->chunkById(100, function ($tasks) use ($organization, &$sent): void {
                foreach ($tasks as $task) {
                    $eventKey = ($task->submitted_at ?: $task->updated_at)->format('YmdHis');
                    $message = "La acción '{$task->title}' está esperando aprobación para su cierre.";
                    if ($this->approvalWorkflow->requires($organization, 'quality') && ! $task->quality_approved_at) {
                        foreach ($this->approvalWorkflow->approvers($organization, 'quality') as $user) {
                            $sent += $this->notifyOnce($organization, $task, $user, 'review_quality', $eventKey, 'Acción pendiente de revisión de Calidad', $message);
                        }
                    }
                    if ($this->approvalWorkflow->requires($organization, 'medical') && ! $task->medical_approved_at) {
                        foreach ($this->approvalWorkflow->approvers($organization, 'medical') as $user) {
                            $sent += $this->notifyOnce($organization, $task, $user, 'review_medical', $eventKey, 'Acción pendiente de revisión de Dirección Médica', $message);
                        }
                    }
                }
            });

        return $sent;
    }

    private function responsibles(Task $task)
    {
        return $task->assignees->push($task->assignee)->filter()->unique('id');
    }

    private function notifyOnce(Organization $organization, Task $task, User $user, string $type, string $eventKey, string $title, string $message): int
    {
        $inserted = DB::table('task_reminder_logs')->insertOrIgnore([
            'organization_id' => $organization->id, 'task_id' => $task->id, 'user_id' => $user->id,
            'type' => $type, 'event_key' => $eventKey, 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if (! $inserted) {
            return 0;
        }
        $user->notify(new TaskReminderNotification($task, $type, $title, $message));

        return 1;
    }
}
