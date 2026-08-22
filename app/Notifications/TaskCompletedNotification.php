<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Task $task, private readonly User $closedBy) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'task_completed',
            'title' => 'Acción cerrada',
            'message' => "La acción '{$this->task->title}' fue cerrada tras completar sus aprobaciones.",
            'task_id' => $this->task->id,
            'closed_by' => $this->closedBy->name,
            'url' => route('tasks.show', $this->task),
        ];
    }
}
