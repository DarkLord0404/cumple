<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinuteCommitmentProposal extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'meeting_minute_id', 'external_key', 'title', 'suggested_responsible', 'suggested_due_date', 'status', 'task_id'];

    public function minute(): BelongsTo
    {
        return $this->belongsTo(MeetingMinute::class, 'meeting_minute_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
