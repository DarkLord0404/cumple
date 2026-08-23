<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImprovementCase extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = ['code', 'institutional_consecutive', 'title', 'finding_source_id', 'reporting_area_id', 'reported_area_id', 'reported_by', 'reported_person_name', 'reported_person_position', 'reported_at', 'action_type', 'finding_description', 'status', 'urgency_score', 'scope_score', 'evolution_score', 'priority_score', 'analysis_method', 'analysis_data', 'immediate_correction', 'root_cause', 'validated_by', 'validated_at', 'validation_notes', 'impact_before', 'impact_after', 'effectiveness_result', 'is_effective', 'effectiveness_evaluated_by', 'effectiveness_evaluated_at', 'closure_notes', 'closed_at'];

    protected function casts(): array
    {
        return ['reported_at' => 'date', 'validated_at' => 'datetime', 'analysis_data' => 'array', 'is_effective' => 'boolean', 'effectiveness_evaluated_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(FindingSource::class, 'finding_source_id');
    }

    public function reportingArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'reporting_area_id');
    }

    public function reportedArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'reported_area_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OfficialDocument::class);
    }

    public function effectivenessEvaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'effectiveness_evaluated_by');
    }

    public function scopeVisibleTo(Builder $query, User $user, bool $canApprove = false): Builder
    {
        if ($user->role === 'administrator') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user, $canApprove): void {
            $query->where('reported_by', $user->id)
                ->orWhereHas('tasks', fn (Builder $tasks) => $tasks
                    ->where('assigned_to', $user->id)
                    ->orWhereHas('assignees', fn (Builder $assignees) => $assignees->whereKey($user->id)));

            if ($canApprove) {
                $query->orWhereHas('tasks', fn (Builder $tasks) => $tasks->where('status', 'in_review'));
            }
        });
    }
}
