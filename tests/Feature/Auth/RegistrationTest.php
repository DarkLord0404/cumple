<?php

namespace Tests\Feature\Auth;

use App\Models\Area;
use App\Models\FindingSource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_organization_and_its_administrator_can_register(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('Crea tu espacio en CUMPLE');

        $this->post(route('register'), [
            'organization_name' => 'Organización Nueva',
            'name' => 'Administradora Nueva',
            'email' => 'admin@nueva.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
        ])->assertRedirect(route('dashboard'));

        $organization = Organization::where('slug', 'organizacion-nueva')->firstOrFail();
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['organization_id' => $organization->id, 'email' => 'admin@nueva.test', 'role' => 'administrator']);
        $this->assertSame(3, Area::withoutGlobalScopes()->where('organization_id', $organization->id)->count());
        $this->assertSame(5, FindingSource::withoutGlobalScopes()->where('organization_id', $organization->id)->count());
    }

    public function test_organizations_cannot_see_each_others_users_or_areas(): void
    {
        $first = Organization::create(['name' => 'Primera', 'slug' => 'primera']);
        $second = Organization::create(['name' => 'Segunda', 'slug' => 'segunda']);
        $admin = User::factory()->create(['organization_id' => $first->id, 'role' => 'administrator', 'name' => 'Administrador visible']);
        User::factory()->create(['organization_id' => $second->id, 'role' => 'administrator', 'name' => 'Administrador aislado']);
        Area::withoutGlobalScopes()->create(['organization_id' => $first->id, 'name' => 'Área propia', 'slug' => 'area-propia']);
        Area::withoutGlobalScopes()->create(['organization_id' => $second->id, 'name' => 'Área ajena', 'slug' => 'area-ajena']);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Administrador visible')
            ->assertDontSee('Administrador aislado')
            ->assertSee('Área propia')
            ->assertDontSee('Área ajena');
    }
}
