<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\FindingSource;
use App\Models\ImprovementCase;
use App\Models\MeetingMinute;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
        $this->assertSame('Nueva tarea asignada', $responsible->fresh()->unreadNotifications->first()?->data['title']);
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

    public function test_action_assignees_can_be_added_and_removed_from_registered_users(): void
    {
        [$creator, $responsible, $case] = $this->caseFixture();
        $replacement = User::factory()->create(['is_active' => true]);
        $task = $this->createTask($creator, $responsible, $case);
        $task->assignees()->sync([$responsible->id]);

        $this->actingAs($creator)->patch(route('tasks.assignees.update', $task), [
            'assignee_ids' => [$replacement->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals([$replacement->id], $task->assignees()->pluck('users.id')->all());
        $this->assertSame($replacement->id, $task->fresh()->assigned_to);
    }

    public function test_action_requires_quality_and_medical_directorate_approval_to_close(): void
    {
        Storage::fake('local');
        [$creator, $responsible, $case] = $this->caseFixture();
        $quality = User::factory()->create(['role' => 'quality']);
        $medicalDirector = User::factory()->create(['role' => 'coordinator_medical']);
        Area::create([
            'name' => 'Dirección Médica',
            'slug' => 'direccion-medica',
            'coordinator_id' => $medicalDirector->id,
        ]);
        $task = $this->createTask($creator, $responsible, $case);

        $this->actingAs($responsible)->post(route('tasks.evidence.store', $task), [
            'evidence' => UploadedFile::fake()->create('soporte.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->actingAs($responsible)->patch(route('tasks.update', $task), [
            'status' => 'in_review', 'progress' => 100,
        ])->assertSessionHasNoErrors();

        $this->actingAs($quality)->patch(route('tasks.review', $task), ['decision' => 'approve'])
            ->assertSessionHasNoErrors();
        $this->assertSame('in_review', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->quality_approved_at);

        $this->actingAs($medicalDirector)->patch(route('tasks.review', $task), ['decision' => 'approve'])
            ->assertSessionHasNoErrors();
        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame(100, $task->progress);
        $this->assertNotNull($task->medical_approved_at);
        $this->assertSame('Acción cerrada', $quality->fresh()->unreadNotifications->first()?->data['title']);
    }

    public function test_configured_quality_only_workflow_closes_with_selected_user(): void
    {
        $organization = Organization::create([
            'name' => 'Organización', 'slug' => 'flujo-calidad', 'is_active' => true, 'approval_policy' => 'quality',
        ]);
        $creator = User::factory()->create(['organization_id' => $organization->id, 'role' => 'administrator']);
        $approver = User::factory()->create(['organization_id' => $organization->id, 'role' => 'collaborator']);
        $area = Area::withoutGlobalScopes()->create([
            'organization_id' => $organization->id, 'name' => 'Operaciones', 'slug' => 'operaciones-flujo', 'is_active' => true,
        ]);
        DB::table('organization_approvers')->insert([
            'organization_id' => $organization->id, 'user_id' => $approver->id,
            'approval_type' => 'quality', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $task = Task::withoutGlobalScopes()->forceCreate([
            'organization_id' => $organization->id, 'area_id' => $area->id, 'code' => 'APR-001',
            'title' => 'Acción configurable', 'created_by' => $creator->id, 'assignee_type' => 'internal',
            'status' => 'in_review', 'progress' => 100, 'submitted_at' => now(), 'due_at' => now()->addWeek(),
        ]);

        $this->actingAs($approver)->patch(route('tasks.review', $task), ['decision' => 'approve'])
            ->assertSessionHasNoErrors();

        $this->assertSame('completed', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->quality_approved_at);
        $this->assertNull($task->fresh()->medical_approved_at);
    }

    public function test_rejected_action_returns_to_ninety_percent_and_notifies_assignees_with_reason(): void
    {
        [$creator, $responsible, $case] = $this->caseFixture();
        $quality = User::factory()->create(['role' => 'quality']);
        $task = $this->createTask($creator, $responsible, $case);
        $task->assignees()->sync([$responsible->id]);
        $task->update(['status' => 'in_review', 'progress' => 100, 'submitted_at' => now()]);

        $this->actingAs($quality)->patch(route('tasks.review', $task), [
            'decision' => 'reject',
            'review_notes' => 'El soporte no permite verificar el cumplimiento.',
        ])->assertSessionHasNoErrors();

        $task->refresh();
        $notification = $responsible->fresh()->unreadNotifications->firstOrFail();
        $this->assertSame('in_progress', $task->status);
        $this->assertSame(90, $task->progress);
        $this->assertSame('Acción devuelta para ajustes', $notification->data['title']);
        $this->assertSame('El soporte no permite verificar el cumplimiento.', $notification->data['reason']);
    }

    public function test_rejection_requires_a_reason_and_submission_forces_one_hundred_percent(): void
    {
        Storage::fake('local');
        [$creator, $responsible, $case] = $this->caseFixture();
        $quality = User::factory()->create(['role' => 'quality']);
        $task = $this->createTask($creator, $responsible, $case);
        $this->actingAs($responsible)->post(route('tasks.evidence.store', $task), [
            'evidence' => UploadedFile::fake()->create('soporte.pdf', 20, 'application/pdf'),
        ]);

        $this->actingAs($responsible)->patch(route('tasks.update', $task), [
            'status' => 'in_review', 'progress' => 35,
        ])->assertSessionHasNoErrors();
        $this->assertSame(100, $task->fresh()->progress);

        $this->actingAs($quality)->patch(route('tasks.review', $task), [
            'decision' => 'reject', 'review_notes' => '',
        ])->assertSessionHasErrors('review_notes');
        $this->assertSame('in_review', $task->fresh()->status);
    }

    public function test_user_can_open_and_mark_their_notification_as_read(): void
    {
        [$creator, $responsible, $case] = $this->caseFixture();
        $this->actingAs($creator)->post(route('cases.tasks.store', $case), [
            'title' => 'Revisar protocolo', 'assignee_type' => 'internal',
            'assignee_ids' => [$responsible->id], 'priority' => 'medium',
            'due_at' => now()->addWeek()->toDateString(),
        ])->assertSessionHasNoErrors();
        $notification = $responsible->fresh()->unreadNotifications->firstOrFail();

        $this->actingAs($responsible)->get(route('notifications.index'))
            ->assertOk()->assertSee('Nueva tarea asignada');
        $this->actingAs($responsible)->patch(route('notifications.read', $notification))
            ->assertRedirect(route('tasks.show', Task::firstOrFail()));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_task_keeps_a_permanent_activity_timeline_and_comments(): void
    {
        Storage::fake('local');
        [$creator, $responsible, $case] = $this->caseFixture();
        $task = $this->createTask($creator, $responsible, $case);

        $this->actingAs($responsible)->patch(route('tasks.update', $task), [
            'status' => 'in_progress', 'progress' => 35, 'review_notes' => 'Primer seguimiento',
        ])->assertSessionHasNoErrors();
        $this->actingAs($responsible)->post(route('tasks.evidence.store', $task), [
            'evidence' => UploadedFile::fake()->create('avance.pdf', 20, 'application/pdf'),
            'description' => 'Soporte parcial',
        ])->assertSessionHasNoErrors();
        $this->actingAs($responsible)->post(route('tasks.comments.store', $task), [
            'body' => 'Se acordó completar el soporte restante el viernes.',
        ])->assertSessionHasNoErrors();

        $events = $task->comments()->orderBy('id')->get();
        $this->assertEquals(['progress_updated', 'evidence_added', 'comment'], $events->pluck('event_type')->all());
        $this->assertSame(35, $events->first()->metadata['after']['progress']);
        $this->actingAs($responsible)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Historial y trazabilidad')
            ->assertSee('Se acordó completar el soporte restante el viernes.');
    }

    public function test_action_cannot_be_submitted_for_review_without_evidence(): void
    {
        [$creator, $responsible, $case] = $this->caseFixture();
        $task = $this->createTask($creator, $responsible, $case);

        $this->actingAs($responsible)->patch(route('tasks.update', $task), [
            'status' => 'in_review', 'progress' => 100,
        ])->assertStatus(422);

        $this->assertSame('pending', $task->fresh()->status);
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
        $creator->update(['role' => 'coordinator_medical']);
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

    public function test_administrator_dashboard_shows_all_organization_actions(): void
    {
        [$creator, $responsible, $case] = $this->caseFixture();
        $administrator = User::factory()->create(['role' => 'administrator']);
        $task = $this->createTask($creator, $responsible, $case, 'Acción visible para administración');

        $this->actingAs($administrator)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($task->title);
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
        $this->assertCount(1, $minute->documentVersions);
        Storage::disk('local')->assertExists($minute->generated_document_path);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($minute->generated_document_path)) === true);
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        $this->assertStringContainsString('Revisar el caso', $documentXml);
        $this->assertStringNotContainsString('{{objetivo}}', $documentXml);
    }

    public function test_minute_draft_can_be_edited_and_word_generations_keep_versions(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $area = Area::create(['name' => 'Urgencias', 'slug' => 'urgencias-versiones']);
        $minute = MeetingMinute::create([
            'number' => '2026-V01', 'title' => 'Título inicial', 'area_id' => $area->id,
            'created_by' => $user->id, 'held_at' => now(), 'status' => 'draft',
        ]);

        $this->actingAs($user)->put(route('minutes.update', $minute), [
            'title' => 'Título corregido', 'held_at' => now()->format('Y-m-d H:i:s'),
            'area_id' => $area->id, 'objective' => 'Objetivo actualizado',
            'external_participant_names' => "Persona Uno\nPersona Dos",
        ])->assertRedirect(route('minutes.show', $minute))->assertSessionHasNoErrors();
        $this->assertSame('Título corregido', $minute->fresh()->title);
        $this->assertCount(2, $minute->fresh()->external_participants);

        $this->actingAs($user)->post(route('minutes.generate', $minute))->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('minutes.generate', $minute))->assertSessionHasNoErrors();
        $versions = $minute->fresh()->documentVersions;
        $this->assertEquals([2, 1], $versions->pluck('version')->all());
        Storage::disk('local')->assertExists($versions[0]->path);
        Storage::disk('local')->assertExists($versions[1]->path);
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

    public function test_dashboard_only_shows_tasks_explicitly_assigned_to_the_user(): void
    {
        [$creator, $responsible, $case] = $this->caseFixture();
        $coordinator = User::factory()->create([
            'role' => 'coordinator_medical',
            'area_id' => $case->reporting_area_id,
        ]);
        $this->createTask($creator, $responsible, $case, 'Tarea privada de otro responsable');

        $this->actingAs($coordinator)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Tarea privada de otro responsable');
    }

    public function test_dashboard_filters_only_offer_values_from_the_users_tasks(): void
    {
        [$creator, $responsible, $case] = $this->caseFixture();
        $this->createTask($creator, $responsible, $case);
        Area::create(['name' => 'Área no utilizada', 'slug' => 'area-no-utilizada']);
        User::factory()->create(['name' => 'Responsable sin tareas']);

        $this->actingAs($responsible)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Área no utilizada')
            ->assertDontSee('Responsable sin tareas')
            ->assertSee('Todos los estados')
            ->assertSee('Todos los responsables')
            ->assertSee('Todas las áreas')
            ->assertDontSee('Vencidas');
    }

    public function test_user_can_open_an_opportunity_with_tasks_and_evidence_relation(): void
    {
        [$creator, $responsible, $case] = $this->caseFixture();
        $task = $this->createTask($creator, $responsible, $case);

        $this->actingAs($responsible)->get(route('cases.show', $case).'#task-'.$task->id)
            ->assertOk()
            ->assertSee($task->title)
            ->assertSee('Seguimiento y evidencias');
    }

    public function test_imported_accreditation_opportunity_is_execution_only(): void
    {
        [$creator, $responsible, $case] = $this->caseFixture();
        $case->update([
            'code' => 'ACR-MA4-1',
            'analysis_method' => 'five_whys',
            'analysis_data' => ['whys' => ['No se había estandarizado el proceso.']],
            'root_cause' => 'Causa raíz institucional.',
        ]);
        $this->createTask($creator, $responsible, $case);

        $this->actingAs($responsible)->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee('No se había estandarizado el proceso.')
            ->assertSee('Guardar responsables')
            ->assertSee('Adjuntar evidencia')
            ->assertDontSee('Agregar acción')
            ->assertDontSee('Guardar priorización')
            ->assertDontSee('Guardar verificación');
    }

    public function test_opportunity_portfolio_can_filter_by_type(): void
    {
        [$creator, $responsible, $correctiveCase] = $this->caseFixture();
        $correctiveCase->update(['title' => 'Hallazgo correctivo oculto']);
        $this->createTask($creator, $responsible, $correctiveCase);
        $improvementCase = $correctiveCase->replicate()->fill([
            'code' => 'H-MEJORA', 'title' => 'Oportunidad visible', 'action_type' => 'improvement',
        ]);
        $improvementCase->save();
        $this->createTask($creator, $responsible, $improvementCase);

        $this->actingAs($responsible)->get(route('cases.index', ['action_type' => 'improvement']))
            ->assertOk()->assertSee('Oportunidad visible')->assertDontSee('Hallazgo correctivo oculto');
    }

    public function test_opportunity_filters_only_offer_values_present_in_the_portfolio(): void
    {
        [$creator, $responsible, $case] = $this->caseFixture();
        $this->createTask($creator, $responsible, $case);
        FindingSource::create(['name' => 'Fuente todavía no utilizada']);
        Area::create(['name' => 'Área sin oportunidades', 'slug' => 'area-sin-oportunidades']);
        User::factory()->create(['name' => 'Usuario sin acciones']);

        $this->actingAs($responsible)->get(route('cases.index'))
            ->assertOk()
            ->assertDontSee('Fuente todavía no utilizada')
            ->assertDontSee('Área sin oportunidades')
            ->assertDontSee('Usuario sin acciones')
            ->assertSee('Todos los tipos')
            ->assertSee('Todos los estados')
            ->assertSee('Servicio del responsable');
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
