<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialDocument extends Model
{
    protected $fillable = ['improvement_case_id', 'uploaded_by', 'document_stage', 'document_type', 'disk', 'path', 'original_name', 'mime_type', 'size', 'notes'];

    public function improvementCase(): BelongsTo
    {
        return $this->belongsTo(ImprovementCase::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
