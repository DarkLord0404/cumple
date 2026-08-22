<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\FindingSource;
use App\Models\ImprovementCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImprovementManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_finding(): void
    {
        $user = User::factory()->create();
        $area = Area::create(['name' => 'Urgencias', 'slug' => 'urgencias']);
        $source = FindingSource::create(['name' => 'No conforme']);

        $response = $this->actingAs($user)->post(route('cases.store'), [
            'title' => 'Hallazgo de prueba', 'finding_source_id' => $source->id,
            'reporting_area_id' => $area->id, 'reported_at' => now()->toDateString(),
            'action_type' => 'corrective', 'finding_description' => 'Descripción institucional.',
        ]);

        $case = ImprovementCase::first();
        $response->assertRedirect(route('cases.show', $case));
        $this->assertSame('Hallazgo de prueba', $case->title);
    }

    public function test_action_can_be_assigned_to_an_internal_user(): void
    {
        [$creator, $responsible, $case] = $this->caseFixture();
        $this->actingAs($creator)->post(route('cases.tasks.store', $case), [
            'title' => 'Socializar protocolo', 'assignee_type' => 'internal',
            'assigned_to' => $responsible->id, 'priority' => 'high',
            'due_at' => now()->addWeek()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame($responsible->id, Task::first()->assigned_to);
    }

    public function test_external_action_requires_and_saves_a_name(): void
    {
        [$creator, , $case] = $this->caseFixture();
        $this->actingAs($creator)->post(route('cases.tasks.store', $case), [
            'title' => 'Entregar soporte externo', 'assignee_type' => 'external',
            'external_assignee_name' => 'Proveedor clínico', 'priority' => 'medium',
            'due_at' => now()->addWeek()->toDateString(),
        ])->assertSessionHasNoErrors();

        $task = Task::first();
        $this->assertSame('external', $task->assignee_type);
        $this->assertSame('Proveedor clínico', $task->external_assignee_name);
        $this->assertNull($task->assigned_to);
    }

    public function test_invima_source_always_uses_cause_effect_analysis(): void
    {
        [$creator, , $case] = $this->caseFixture(true);
        $this->actingAs($creator)->patch(route('cases.prioritization.update', $case), [
            'urgency_score' => 2, 'scope_score' => 3, 'evolution_score' => 4,
            'analysis_method' => 'five_whys',
        ])->assertSessionHasNoErrors();

        $case->refresh();
        $this->assertSame(9, $case->priority_score);
        $this->assertSame('cause_effect', $case->analysis_method);
    }

    public function test_original_official_document_is_stored_privately(): void
    {
        Storage::fake('local');
        [$creator, , $case] = $this->caseFixture();
        $this->actingAs($creator)->post(route('cases.documents.store', $case), [
            'file' => UploadedFile::fake()->create('no-conforme.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'document_stage' => 'original', 'document_type' => 'nonconformity',
        ])->assertSessionHasNoErrors();

        $document = $case->documents()->first();
        $this->assertSame('original', $document->document_stage);
        Storage::disk('local')->assertExists($document->path);
    }

    private function caseFixture(bool $invima = false): array
    {
        $creator = User::factory()->create();
        $responsible = User::factory()->create();
        $area = Area::create(['name' => 'Urgencias', 'slug' => 'urgencias']);
        $source = FindingSource::create(['name' => $invima ? 'Auditoría INVIMA BPM' : 'No conforme', 'is_invima' => $invima]);
        $case = ImprovementCase::create([
            'code' => 'H-TEST', 'title' => 'Caso', 'finding_source_id' => $source->id,
            'reporting_area_id' => $area->id, 'reported_by' => $creator->id,
            'reported_at' => now(), 'action_type' => 'corrective',
            'finding_description' => 'Descripción',
        ]);

        return [$creator, $responsible, $case];
    }
}
