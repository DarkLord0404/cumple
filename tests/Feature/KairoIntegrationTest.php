<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Models\MeetingMinute;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KairoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kairo_can_create_and_idempotently_update_a_draft_for_its_organization(): void
    {
        $organization = Organization::create(['name' => 'Clínica', 'slug' => 'clinica', 'is_active' => true]);
        $admin = User::factory()->create(['organization_id' => $organization->id, 'role' => 'administrator']);
        $participant = User::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Marcela Pérez',
            'email' => 'marcela@example.com',
            'is_active' => true,
        ]);
        $token = str_repeat('a', 80);
        IntegrationConnection::create([
            'organization_id' => $organization->id,
            'created_by' => $admin->id,
            'provider' => 'kairo',
            'name' => 'Kairo Meet',
            'token_hash' => hash('sha256', $token),
            'is_active' => true,
        ]);

        $payload = [
            'external_reference' => 'reunion-42',
            'title' => 'Comité de calidad',
            'held_at' => '2026-08-22T10:00:00-05:00',
            'minutes_markdown' => <<<'MD'
# Acta
- **Objetivo de la reunion:** Revisar resultados del comité.

## Temas tratados
1. Indicadores
2. Plan de trabajo

## Texto del acta (lo discutido)
Se revisaron los resultados y sus causas.

## Tareas y acciones pendientes
| Tarea | Responsable | Fecha limite |
|---|---|---|
| Actualizar protocolo | Marcela Pérez | 30/08/2026 |

## Conclusion
Se aprobó continuar el plan.
MD,
            'participants' => [
                ['name' => 'Marcela Pérez', 'email' => 'marcela@example.com'],
                ['name' => 'Persona externa'],
            ],
        ];

        $this->withToken($token)->postJson('/api/integrations/kairo/meetings', $payload)
            ->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('matched_participants', 1)
            ->assertJsonPath('external_participants', 1);

        $payload['title'] = 'Comité de calidad actualizado';
        $this->withToken($token)->postJson('/api/integrations/kairo/meetings', $payload)->assertOk();

        $this->assertSame(1, MeetingMinute::withoutGlobalScopes()->count());
        $minute = MeetingMinute::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($organization->id, $minute->organization_id);
        $this->assertSame('Comité de calidad actualizado', $minute->title);
        $this->assertSame('draft', $minute->status);
        $this->assertSame('Revisar resultados del comité.', $minute->objective);
        $this->assertStringContainsString('1. Indicadores', $minute->agenda);
        $this->assertSame('Se revisaron los resultados y sus causas.', $minute->development);
        $this->assertSame('Se aprobó continuar el plan.', $minute->decisions);
        $this->assertSame('Actualizar protocolo', $minute->external_payload['parsed_commitments'][0]['title']);
        $this->assertEquals([$participant->id], $minute->attendees()->pluck('users.id')->all());
        $this->assertSame('Persona externa', $minute->external_participants[0]['name']);
        $this->assertCount(0, $minute->tasks);
    }

    public function test_kairo_minutes_are_hidden_unless_organization_configuration_allows_access(): void
    {
        $organization = Organization::create([
            'name' => 'Organización privada', 'slug' => 'privada', 'is_active' => true,
            'kairo_minute_visibility' => 'administrators',
        ]);
        $admin = User::factory()->create(['organization_id' => $organization->id, 'role' => 'administrator']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'collaborator']);
        $minute = MeetingMinute::withoutGlobalScopes()->create([
            'organization_id' => $organization->id, 'number' => 'KAIRO-1', 'title' => 'Acta privada',
            'created_by' => $admin->id, 'held_at' => now(), 'status' => 'draft',
            'source_system' => 'kairo', 'external_reference' => 'private-1',
        ]);

        $this->actingAs($user)->get(route('minutes.index'))->assertDontSeeText('Acta privada');
        $this->actingAs($user)->get(route('minutes.show', $minute))->assertForbidden();

        $organization->update(['kairo_minute_visibility' => 'everyone']);
        $user->unsetRelation('organization');
        $this->actingAs($user)->get(route('minutes.index'))->assertSeeText('Acta privada');
        $this->actingAs($user)->get(route('minutes.show', $minute))->assertOk();
    }

    public function test_kairo_endpoint_rejects_an_invalid_token(): void
    {
        $this->withToken(str_repeat('x', 80))->postJson('/api/integrations/kairo/meetings', [])->assertUnauthorized();
    }
}
