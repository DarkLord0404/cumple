<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly User $reviewedBy,
        private readonly string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'task_rejected',
            'title' => 'Acción devuelta para ajustes',
            'message' => "{$this->reviewedBy->name} devolvió '{$this->task->title}'. Causal: {$this->reason}",
            'reason' => $this->reason,
            'reviewed_by' => $this->reviewedBy->name,
            'task_id' => $this->task->id,
            'url' => route('tasks.show', $this->task),
        ];
    }
}
