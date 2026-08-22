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
            'area_id' => $area->id, 'role' => 'coordinator',
            'password' => 'Temporal2026', 'password_confirmation' => 'Temporal2026',
        ]);

        $response->assertRedirect()->assertSessionHas('status')->assertSessionHasNoErrors();
        $user = User::where('email', 'coordinador@example.com')->firstOrFail();
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Temporal2026', $user->password));
        $this->assertSame('coordinator', $user->role);
    }

    public function test_user_form_displays_validation_errors(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $response = $this->actingAs($administrator)->from(route('users.index'))->followingRedirects()->post(route('users.store'), [
            'name' => '', 'email' => 'correo-invalido', 'role' => 'collaborator',
            'password' => '123', 'password_confirmation' => '456',
        ]);
        $response->assertOk()->assertSee('No fue posible crear el usuario')->assertSee('al menos 8 caracteres');
    }

    public function test_non_administrator_cannot_create_users(): void
    {
        $collaborator = User::factory()->create(['role' => 'collaborator']);
        $this->actingAs($collaborator)->post(route('users.store'), [])->assertForbidden();
    }
}
