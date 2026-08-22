<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImprovementCase extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'title', 'finding_source_id', 'reporting_area_id', 'reported_area_id', 'reported_by', 'reported_at', 'action_type', 'finding_description', 'status', 'urgency_score', 'scope_score', 'evolution_score', 'priority_score', 'analysis_method', 'analysis_data', 'immediate_correction', 'root_cause', 'validated_by', 'validated_at', 'validation_notes'];

    protected function casts(): array
    {
        return ['reported_at' => 'date', 'validated_at' => 'datetime', 'analysis_data' => 'array'];
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
}
