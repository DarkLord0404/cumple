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
            'minutes_markdown' => '## Desarrollo\nContenido inicial.',
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
        $this->assertEquals([$participant->id], $minute->attendees()->pluck('users.id')->all());
        $this->assertSame('Persona externa', $minute->external_participants[0]['name']);
        $this->assertCount(0, $minute->tasks);
    }

    public function test_kairo_endpoint_rejects_an_invalid_token(): void
    {
        $this->withToken(str_repeat('x', 80))->postJson('/api/integrations/kairo/meetings', [])->assertUnauthorized();
    }
}
