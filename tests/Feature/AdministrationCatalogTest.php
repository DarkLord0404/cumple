<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\FindingSource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrationCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_update_their_organization_name(): void
    {
        $organization = Organization::create([
            'name' => 'Nombre anterior', 'slug' => 'nombre-anterior', 'is_active' => true,
        ]);
        $administrator = User::factory()->create([
            'organization_id' => $organization->id, 'role' => 'administrator',
        ]);

        $this->actingAs($administrator)->patch(route('organization.update'), [
            'name' => '  Nueva Organización Principal  ',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Nueva Organización Principal', $organization->fresh()->name);
        $this->assertSame('nombre-anterior', $organization->fresh()->slug);
    }

    public function test_administrator_can_manage_areas_and_assign_a_coordinator(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $coordinator = User::factory()->create(['role' => 'coordinator_medical', 'is_active' => true]);

        $this->actingAs($administrator)->post(route('areas.store'), [
            'name' => 'Hospitalización',
            'description' => 'Servicios de hospitalización',
            'coordinator_id' => $coordinator->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $area = Area::where('slug', 'hospitalizacion')->firstOrFail();
        $this->assertSame($coordinator->id, $area->coordinator_id);
        $this->assertSame($area->id, $coordinator->fresh()->area_id);

        $this->actingAs($administrator)->patch(route('areas.update', $area), [
            'name' => 'Hospitalización adultos',
            'description' => 'Área actualizada',
            'coordinator_id' => $coordinator->id,
            'is_active' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('areas', [
            'id' => $area->id,
            'slug' => 'hospitalizacion-adultos',
            'is_active' => false,
        ]);
    }

    public function test_administrator_can_manage_finding_sources(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($administrator)->post(route('sources.store'), [
            'name' => 'Auditoría externa',
            'is_invima' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $source = FindingSource::where('name', 'Auditoría externa')->firstOrFail();
        $this->actingAs($administrator)->patch(route('sources.update', $source), [
            'name' => 'Visita INVIMA',
            'is_invima' => true,
            'is_active' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $source->refresh();
        $this->assertTrue($source->is_invima);
        $this->assertFalse($source->is_active);
    }

    public function test_non_administrator_cannot_manage_catalogs(): void
    {
        $user = User::factory()->create(['role' => 'collaborator']);

        $this->actingAs($user)->get(route('administration.catalogs'))->assertForbidden();
        $this->actingAs($user)->get(route('administration.organization'))->assertForbidden();
        $this->actingAs($user)->get(route('administration.areas'))->assertForbidden();
        $this->actingAs($user)->get(route('administration.sources'))->assertForbidden();
        $this->actingAs($user)->post(route('areas.store'), [])->assertForbidden();
        $this->actingAs($user)->post(route('sources.store'), [])->assertForbidden();
    }

    public function test_configuration_hub_links_to_independent_sections(): void
    {
        $organization = Organization::create([
            'name' => 'Organización modular', 'slug' => 'organizacion-modular', 'is_active' => true,
        ]);
        $administrator = User::factory()->create([
            'organization_id' => $organization->id, 'role' => 'administrator',
        ]);

        $this->actingAs($administrator)->get(route('administration.catalogs'))
            ->assertOk()
            ->assertSee(route('administration.organization'))
            ->assertSee(route('administration.areas'))
            ->assertSee(route('administration.sources'));
        $this->actingAs($administrator)->get(route('administration.organization'))->assertOk()->assertSee('Organización modular');
        $this->actingAs($administrator)->get(route('administration.areas'))->assertOk()->assertSee('Nueva área o servicio');
        $this->actingAs($administrator)->get(route('administration.sources'))->assertOk()->assertSee('Nueva fuente');
    }

    public function test_administrator_can_delete_an_unused_area(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $coordinator = User::factory()->create(['role' => 'coordinator_nursing_junior', 'is_active' => true]);
        $area = Area::create([
            'name' => 'Área temporal',
            'slug' => 'area-temporal',
            'coordinator_id' => $coordinator->id,
        ]);
        $coordinator->update(['area_id' => $area->id]);

        $this->actingAs($administrator)->delete(route('areas.destroy', $area))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('areas', ['id' => $area->id]);
        $this->assertNull($coordinator->fresh()->area_id);
    }
}
