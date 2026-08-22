<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            $organizationId = Auth::hasUser() ? Auth::user()->organization_id : null;
            if ($organizationId) {
                $builder->where($builder->qualifyColumn('organization_id'), $organizationId);
            }
        });

        static::creating(function ($model): void {
            if (! $model->organization_id && Auth::hasUser() && Auth::user()->organization_id) {
                $model->organization_id = Auth::user()->organization_id;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
