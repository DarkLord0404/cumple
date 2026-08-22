<?php

namespace App\Services;

use App\Models\Area;
use App\Models\FindingSource;
use App\Models\Organization;

class OrganizationDefaults
{
    public function createFor(Organization $organization): void
    {
        collect([
            ['name' => 'Dirección', 'slug' => 'direccion'],
            ['name' => 'Calidad', 'slug' => 'calidad'],
            ['name' => 'Operaciones', 'slug' => 'operaciones'],
        ])->each(fn (array $area) => Area::withoutGlobalScopes()->create($area + ['organization_id' => $organization->id]));

        collect(['Auditoría', 'Autorreporte', 'No conformidad', 'Oportunidad de mejora', 'Reunión o comité'])
            ->each(fn (string $name) => FindingSource::withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'name' => $name,
                'is_invima' => false,
                'is_active' => true,
            ]));
    }
}
