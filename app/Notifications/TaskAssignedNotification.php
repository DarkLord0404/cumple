<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Task $task, private readonly User $assignedBy) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'task_assigned',
            'title' => 'Nueva tarea asignada',
            'message' => "{$this->assignedBy->name} te asignó: {$this->task->title}",
            'task_id' => $this->task->id,
            'url' => route('tasks.show', $this->task),
        ];
    }
}
