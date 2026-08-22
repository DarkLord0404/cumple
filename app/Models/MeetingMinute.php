<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeetingMinute extends Model
{
    protected $fillable = ['number', 'title', 'meeting_type', 'organizer', 'area_id', 'created_by', 'held_at', 'location', 'external_participants', 'objective', 'agenda', 'development', 'decisions', 'status', 'approved_at', 'source_system', 'external_reference', 'source_document_path', 'generated_document_path'];

    use BelongsToOrganization, SoftDeletes;

    protected function casts(): array
    {
        return ['held_at' => 'datetime', 'approved_at' => 'datetime', 'external_participants' => 'array'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('attended')->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
