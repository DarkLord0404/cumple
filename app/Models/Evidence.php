<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evidence extends Model
{
    use BelongsToOrganization;

    protected $table = 'evidences';

    protected $fillable = ['task_id', 'uploaded_by', 'description', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
