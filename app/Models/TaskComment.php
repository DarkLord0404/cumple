<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['task_id', 'user_id', 'body', 'event_type', 'metadata', 'is_internal'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean', 'metadata' => 'array'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
