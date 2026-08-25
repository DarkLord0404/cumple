<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Task extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = ['code', 'title', 'description', 'expected_result', 'required_resources', 'area_id', 'meeting_minute_id', 'improvement_case_id', 'created_by', 'assigned_to', 'assignee_type', 'external_assignee_name', 'external_assignee_email', 'priority', 'status', 'progress', 'due_at', 'started_at', 'submitted_at', 'completed_at', 'reviewed_by', 'review_notes', 'quality_approved_by', 'quality_approved_at', 'medical_approved_by', 'medical_approved_at'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'quality_approved_at' => 'datetime',
            'medical_approved_at' => 'datetime',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function minute(): BelongsTo
    {
        return $this->belongsTo(MeetingMinute::class, 'meeting_minute_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function improvementCase(): BelongsTo
    {
        return $this->belongsTo(ImprovementCase::class);
    }

    public function getResponsibleNameAttribute(): string
    {
        if ($this->assignee_type === 'external') {
            return $this->external_assignee_name ?: 'Responsable externo';
        }

        $names = $this->assignees->pluck('name');

        return $names->isNotEmpty() ? $names->join(', ') : ($this->assignee?->name ?: 'Sin asignar');
    }

    public function getDisplayTitleAttribute(): string
    {
        if (! $this->description || ! Str::startsWith($this->description, $this->title)) {
            return $this->title;
        }

        $fullAction = trim(Str::before($this->description, "\n\n"));

        return mb_strlen($fullAction) > mb_strlen($this->title) ? $fullAction : $this->title;
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function qualityApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quality_approved_by');
    }

    public function medicalApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medical_approved_by');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }
}
