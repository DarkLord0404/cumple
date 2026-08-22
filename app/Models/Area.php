<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'name', 'slug', 'description', 'coordinator_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }
}
