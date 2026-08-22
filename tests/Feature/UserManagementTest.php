<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_an_active_verified_user(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $area = Area::create(['name' => 'Urgencias', 'slug' => 'urgencias']);

        $response = $this->actingAs($administrator)->post(route('users.store'), [
            'name' => 'Coordinador Urgencias', 'email' => 'coordinador@example.com',
            'area_id' => $area->id, 'role' => 'coordinator_medical',
            'password' => 'Temporal2026', 'password_confirmation' => 'Temporal2026',
        ]);

        $response->assertRedirect()->assertSessionHas('status')->assertSessionHasNoErrors();
        $user = User::where('email', 'coordinador@example.com')->firstOrFail();
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Temporal2026', $user->password));
        $this->assertSame('coordinator_medical', $user->role);
    }

    public function test_user_form_displays_validation_errors(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $response = $this->actingAs($administrator)->from(route('users.index'))->followingRedirects()->post(route('users.store'), [
            'name' => '', 'email' => 'correo-invalido', 'role' => 'collaborator',
            'password' => '123', 'password_confirmation' => '456',
        ]);
        $response->assertOk()->assertSee('No fue posible guardar los cambios')->assertSee('al menos 8 caracteres');
    }

    public function test_non_administrator_cannot_create_users(): void
    {
        $collaborator = User::factory()->create(['role' => 'collaborator']);
        $this->actingAs($collaborator)->post(route('users.store'), [])->assertForbidden();
    }

    public function test_administrator_can_update_a_user_and_reset_their_password(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $user = User::factory()->create(['role' => 'collaborator', 'is_active' => true]);

        $this->actingAs($administrator)->patch(route('users.update', $user), [
            'name' => 'Usuario actualizado',
            'email' => 'actualizado@example.com',
            'role' => 'coordinator_nursing_junior',
            'is_active' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($administrator)->patch(route('users.password.update', $user), [
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('coordinator_nursing_junior', $user->role);
        $this->assertFalse($user->is_active);
        $this->assertTrue(Hash::check('NuevaClave2026', $user->password));
    }

    public function test_administrator_cannot_deactivate_their_own_account(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator', 'is_active' => true]);

        $this->actingAs($administrator)->patch(route('users.update', $administrator), [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'role' => 'administrator',
            'is_active' => false,
        ])->assertSessionHasErrors('user');

        $this->assertTrue($administrator->fresh()->is_active);
    }

    public function test_coordinator_role_and_area_are_synchronized_with_area_management(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $area = Area::create(['name' => 'Urgencias', 'slug' => 'urgencias']);
        $user = User::factory()->create(['role' => 'collaborator']);

        $this->actingAs($administrator)->patch(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'area_id' => $area->id,
            'role' => 'coordinator_medical',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame($user->id, $area->fresh()->coordinator_id);
    }
}
