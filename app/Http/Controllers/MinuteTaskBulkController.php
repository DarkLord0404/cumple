<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\MeetingMinute;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Services\KairoMinuteVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MinuteTaskBulkController extends Controller
{
    public function __construct(private readonly KairoMinuteVisibility $visibility) {}

    public function edit(Request $request, MeetingMinute $minute): View
    {
        $this->authorizeMinute($request, $minute);

        return view('minutes.tasks-bulk-edit', [
            'minute' => $minute->load(['tasks.area', 'tasks.assignees', 'tasks.assignee']),
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function updateAll(Request $request, MeetingMinute $minute): RedirectResponse
    {
        $this->authorizeMinute($request, $minute);
        $data = $request->validate([
            'tasks' => ['required', 'array'],
            'tasks.*.title' => ['required', 'string', 'max:255'],
            'tasks.*.area_id' => ['required', Rule::exists('areas', 'id')->where('organization_id', $request->user()->organization_id)],
            'tasks.*.due_at' => ['required', 'date'],
            'tasks.*.expected_result' => ['nullable', 'string'],
            'tasks.*.assignee_type' => ['required', Rule::in(['internal', 'external'])],
            'tasks.*.assignee_ids' => ['nullable', 'array'],
            'tasks.*.assignee_ids.*' => ['integer', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
            'tasks.*.external_assignee_name' => ['nullable', 'string', 'max:255'],
        ]);
        $taskIds = collect(array_keys($data['tasks']))->map(fn ($id) => (int) $id);
        $tasks = $minute->tasks()->whereIn('id', $taskIds)->get()->keyBy('id');
        abort_unless($tasks->count() === $taskIds->unique()->count(), 404);

        DB::transaction(function () use ($data, $tasks, $request): void {
            foreach ($data['tasks'] as $taskId => $values) {
                $task = $tasks->get((int) $taskId);
                abort_if(in_array($task->status, ['in_review', 'completed'], true), 422, "La tarea {$task->code} no se puede editar por su estado.");
                $assigneeIds = collect($values['assignee_ids'] ?? [])->filter()->unique()->values();
                if ($values['assignee_type'] === 'internal') {
                    abort_if($assigneeIds->isEmpty(), 422, "Selecciona responsables para {$task->code}.");
                    $values['assigned_to'] = $assigneeIds->first();
                    $values['external_assignee_name'] = null;
                } else {
                    abort_if(blank($values['external_assignee_name'] ?? null), 422, "Escribe el responsable externo de {$task->code}.");
                    $values['assigned_to'] = null;
                    $assigneeIds = collect();
                }
                unset($values['assignee_ids']);
                $newAssignees = $assigneeIds->diff($task->assignees()->pluck('users.id'));
                $task->update($values);
                $task->assignees()->sync($assigneeIds);
                $this->notifyNewAssignees($task, $newAssignees, $request->user());
            }
        });
        $minute->update(['status' => 'draft']);

        return back()->with('status', 'Todas las tareas editables fueron actualizadas.');
    }

    public function applyBulk(Request $request, MeetingMinute $minute): RedirectResponse
    {
        $this->authorizeMinute($request, $minute);
        $data = $request->validate([
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', 'distinct'],
            'bulk_action' => ['required', Rule::in(['update', 'delete'])],
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('organization_id', $request->user()->organization_id)],
            'due_at' => ['nullable', 'date'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
        ]);
        $tasks = $minute->tasks()->whereIn('id', $data['task_ids'])->get();
        abort_unless($tasks->count() === count(array_unique($data['task_ids'])), 404);
        abort_if($tasks->contains(fn (Task $task) => in_array($task->status, ['in_review', 'completed'], true)), 422, 'La selección contiene tareas en revisión o cerradas.');

        if ($data['bulk_action'] === 'delete') {
            DB::transaction(fn () => $tasks->each->delete());
            $minute->update(['status' => 'draft']);
            return redirect()->route('minutes.tasks.bulk.edit', $minute)->with('status', $tasks->count().' tareas eliminadas.');
        }

        $changes = array_filter(['area_id' => $data['area_id'] ?? null, 'due_at' => $data['due_at'] ?? null], fn ($value) => filled($value));
        $assigneeIds = collect($data['assignee_ids'] ?? [])->filter()->unique()->values();
        abort_if(empty($changes) && $assigneeIds->isEmpty(), 422, 'Selecciona al menos un cambio masivo.');
        DB::transaction(function () use ($tasks, $changes, $assigneeIds, $request): void {
            foreach ($tasks as $task) {
                $task->update($changes);
                if ($assigneeIds->isNotEmpty()) {
                    $newAssignees = $assigneeIds->diff($task->assignees()->pluck('users.id'));
                    $task->update(['assignee_type' => 'internal', 'assigned_to' => $assigneeIds->first(), 'external_assignee_name' => null]);
                    $task->assignees()->sync($assigneeIds);
                    $this->notifyNewAssignees($task, $newAssignees, $request->user());
                }
            }
        });
        $minute->update(['status' => 'draft']);

        return back()->with('status', 'Cambios masivos aplicados a '.$tasks->count().' tareas.');
    }

    private function authorizeMinute(Request $request, MeetingMinute $minute): void
    {
        abort_unless($this->visibility->canView($minute, $request->user()), 403);
    }

    private function notifyNewAssignees(Task $task, $ids, User $actor): void
    {
        if ($ids->isNotEmpty()) {
            Notification::send(User::whereIn('id', $ids)->get(), new TaskAssignedNotification($task, $actor));
        }
    }
}
