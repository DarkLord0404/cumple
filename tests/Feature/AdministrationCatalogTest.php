<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\FindingSource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $this->actingAs($administrator)->get(route('administration.areas'))->assertOk()->assertSee('Crear área o servicio');
        $this->actingAs($administrator)->get(route('administration.sources'))->assertOk()->assertSee('Nueva fuente');
        $this->actingAs($administrator)->get(route('administration.reminders'))->assertOk()->assertSee('Recordatorios y alertas');
        $this->actingAs($administrator)->get(route('administration.approvals'))->assertOk()->assertSee('Roles y aprobaciones');
    }

    public function test_administrator_can_select_approvers_and_closure_policy(): void
    {
        $organization = Organization::create([
            'name' => 'Organización', 'slug' => 'organizacion-aprobaciones', 'is_active' => true,
        ]);
        $administrator = User::factory()->create(['organization_id' => $organization->id, 'role' => 'administrator']);
        $quality = User::factory()->create(['organization_id' => $organization->id, 'role' => 'collaborator']);
        $medical = User::factory()->create(['organization_id' => $organization->id, 'role' => 'collaborator']);

        $this->actingAs($administrator)->patch(route('approvals.update'), [
            'approval_policy' => 'both',
            'quality_approver_ids' => [$quality->id],
            'medical_approver_ids' => [$medical->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('both', $organization->fresh()->approval_policy);
        $this->assertDatabaseHas('organization_approvers', ['user_id' => $quality->id, 'approval_type' => 'quality']);
        $this->assertDatabaseHas('organization_approvers', ['user_id' => $medical->id, 'approval_type' => 'medical']);
    }

    public function test_administrator_can_upload_an_organization_minute_template(): void
    {
        Storage::fake('local');
        $organization = Organization::create([
            'name' => 'Organización', 'slug' => 'organizacion-plantilla', 'is_active' => true,
        ]);
        $administrator = User::factory()->create(['organization_id' => $organization->id, 'role' => 'administrator']);

        $this->actingAs($administrator)->put(route('minute-template.update'), [
            'template' => UploadedFile::fake()->create('formato-acta.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $organization->refresh();
        $this->assertSame('formato-acta.docx', $organization->minute_template_name);
        Storage::disk('local')->assertExists($organization->minute_template_path);
    }

    public function test_administrator_can_configure_reminders_for_their_organization(): void
    {
        $organization = Organization::create([
            'name' => 'Organización', 'slug' => 'organizacion-alertas', 'is_active' => true,
        ]);
        $administrator = User::factory()->create(['organization_id' => $organization->id, 'role' => 'administrator']);

        $this->actingAs($administrator)->patch(route('reminders.update'), [
            'reminders_enabled' => 1, 'reminder_days' => [14, 3, 1],
            'overdue_alerts_enabled' => 1, 'review_alerts_enabled' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $organization->refresh();
        $this->assertSame([14, 3, 1], $organization->reminder_days);
        $this->assertTrue($organization->overdue_alerts_enabled);
        $this->assertFalse($organization->review_alerts_enabled);
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
