<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\FindingSource;
use App\Models\ImprovementCase;
use App\Models\MeetingMinute;
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

    public function test_action_can_have_multiple_internal_assignees(): void
    {
        [$creator, , $case] = $this->caseFixture();
        $responsibles = User::factory()->count(2)->create();

        $this->actingAs($creator)->post(route('cases.tasks.store', $case), [
            'title' => 'Implementar acción transversal', 'assignee_type' => 'internal',
            'assignee_ids' => $responsibles->pluck('id')->all(), 'priority' => 'high',
            'due_at' => now()->addMonth()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $task = Task::firstOrFail();
        $this->assertEqualsCanonicalizing($responsibles->pluck('id')->all(), $task->assignees()->pluck('users.id')->all());
        $this->assertStringContainsString($responsibles[0]->name, $task->responsible_name);
        $this->assertStringContainsString($responsibles[1]->name, $task->responsible_name);
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

    public function test_effective_plan_can_only_close_when_all_actions_are_completed(): void
    {
        [$creator, $responsible, $case] = $this->caseFixture();
        $creator->update(['role' => 'coordinator']);
        $task = $this->createTask($creator, $responsible, $case);
        $payload = ['impact_before' => 'Incumplimiento', 'impact_after' => 'Cumplimiento sostenido', 'effectiveness_result' => 'Se alcanzó la meta', 'is_effective' => 1];

        $this->actingAs($creator)->patch(route('cases.effectiveness.update', $case), $payload)->assertStatus(422);
        $task->update(['status' => 'completed', 'progress' => 100]);
        $this->actingAs($creator)->patch(route('cases.effectiveness.update', $case), $payload)->assertSessionHasNoErrors();

        $this->assertSame('closed', $case->fresh()->status);
        $this->assertNotNull($case->fresh()->closed_at);
    }

    public function test_ineffective_plan_is_reopened(): void
    {
        [$creator, , $case] = $this->caseFixture();
        $creator->update(['role' => 'quality']);
        $case->update(['status' => 'closed', 'closed_at' => now()]);
        $this->actingAs($creator)->patch(route('cases.effectiveness.update', $case), [
            'impact_before' => 'Resultado inicial', 'impact_after' => 'Sin cambio',
            'effectiveness_result' => 'No se alcanzó la meta', 'is_effective' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame('action_plan', $case->fresh()->status);
        $this->assertNull($case->fresh()->closed_at);
    }

    public function test_collaborator_dashboard_only_shows_assigned_actions(): void
    {
        [$creator, $responsible, $case] = $this->caseFixture();
        $assigned = $this->createTask($creator, $responsible, $case, 'Mi acción');
        $other = User::factory()->create();
        $this->createTask($creator, $other, $case, 'Acción ajena');

        $this->actingAs($responsible)->get(route('dashboard'))
            ->assertOk()->assertSee($assigned->title)->assertDontSee('Acción ajena');
    }

    public function test_minute_generates_an_institutional_word_copy(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $area = Area::create(['name' => 'Urgencias', 'slug' => 'urgencias']);
        $minute = MeetingMinute::create([
            'number' => '2026-001', 'title' => 'Revisión de caso', 'area_id' => $area->id,
            'created_by' => $user->id, 'held_at' => now(), 'location' => 'Virtual',
            'objective' => 'Revisar el caso', 'development' => 'Se analizó la situación.',
            'status' => 'draft',
        ]);

        $this->actingAs($user)->post(route('minutes.generate', $minute))->assertSessionHasNoErrors();
        $minute->refresh();
        $this->assertSame('ready', $minute->status);
        Storage::disk('local')->assertExists($minute->generated_document_path);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($minute->generated_document_path)) === true);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        $this->assertStringContainsString('Revisar el caso', $documentXml);
        $this->assertStringNotContainsString('{{objetivo}}', $documentXml);
    }

    public function test_institutional_excel_is_read_without_ai_and_prefills_the_finding(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $area = Area::create(['name' => 'Urgencias', 'slug' => 'urgencias']);
        $source = FindingSource::create(['name' => 'No conforme']);
        $upload = UploadedFile::fake()->createWithContent(
            'reporte-institucional.xlsx',
            file_get_contents(base_path('tests/Fixtures/institutional-finding.xlsx')),
        );

        $response = $this->actingAs($user)->post(route('cases.import'), ['excel' => $upload]);
        $response->assertRedirect(route('cases.create'))->assertSessionHas('excel_import');
        $import = session('excel_import');
        $this->assertSame('NC-TEST-001', $import['institutional_consecutive']);
        $this->assertSame($area->id, $import['reporting_area_id']);
        $this->assertSame($source->id, $import['finding_source_id']);
        $this->assertSame('cause_effect', $import['analysis_method']);
        $this->assertSame(5, $import['priority_score']);

        $this->actingAs($user)->post(route('cases.store'), [
            'title' => $import['title'], 'finding_source_id' => $import['finding_source_id'],
            'reporting_area_id' => $import['reporting_area_id'], 'reported_area_id' => $import['reported_area_id'],
            'reported_at' => $import['reported_at'], 'action_type' => $import['action_type'],
            'finding_description' => $import['finding_description'], 'institutional_consecutive' => $import['institutional_consecutive'],
            'reported_person_name' => $import['reported_person_name'], 'reported_person_position' => $import['reported_person_position'],
            'urgency_score' => $import['urgency_score'], 'scope_score' => $import['scope_score'],
            'evolution_score' => $import['evolution_score'], 'priority_score' => $import['priority_score'],
            'analysis_method' => $import['analysis_method'], 'temporary_path' => $import['temporary_path'],
            'original_name' => $import['original_name'],
        ])->assertSessionHasNoErrors();

        $case = ImprovementCase::firstOrFail();
        $this->assertSame('Persona reportante', $case->reported_person_name);
        $this->assertCount(1, $case->documents);
        Storage::disk('local')->assertExists($case->documents->first()->path);
    }

    public function test_dashboard_can_filter_tasks_by_search_text(): void
    {
        [$creator, $responsible, $case] = $this->caseFixture();
        $this->createTask($creator, $responsible, $case, 'Auditar protocolo de urgencias');
        $this->createTask($creator, $responsible, $case, 'Actualizar guía quirúrgica');

        $this->actingAs($responsible)->get(route('dashboard', ['q' => 'urgencias']))
            ->assertOk()
            ->assertSee('Auditar protocolo de urgencias')
            ->assertDontSee('Actualizar guía quirúrgica');
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

    private function createTask(User $creator, User $responsible, ImprovementCase $case, string $title = 'Acción de prueba'): Task
    {
        return Task::create([
            'code' => 'AC-'.fake()->unique()->numerify('#####'), 'title' => $title,
            'area_id' => $case->reporting_area_id, 'improvement_case_id' => $case->id,
            'created_by' => $creator->id, 'assigned_to' => $responsible->id,
            'assignee_type' => 'internal', 'priority' => 'medium', 'status' => 'pending',
            'due_at' => now()->addWeek(),
        ]);
    }
}
