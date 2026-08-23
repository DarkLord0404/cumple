<?php

namespace App\Http\Controllers;

use App\Models\ImprovementCase;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCompletedNotification;
use App\Notifications\TaskRejectedNotification;
use App\Services\ApprovalWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CaseTaskController extends Controller
{
    public function __construct(private readonly ApprovalWorkflow $approvalWorkflow) {}

    public function show(Request $request, Task $task): View
    {
        $this->authorizeTaskManagement($request, $task);
        $user = $request->user();

        return view('tasks.show', [
            'task' => $task->load(['improvementCase', 'minute', 'area', 'assignees', 'evidences.uploader', 'qualityApprover', 'medicalApprover', 'comments.author']),
            'users' => User::where('is_active', true)->with('area')->orderBy('name')->get(),
            'canReviewAsQuality' => $this->approvalWorkflow->isApprover($user, 'quality'),
            'canReviewAsMedicalDirectorate' => $this->approvalWorkflow->isApprover($user, 'medical'),
            'requiresQuality' => $this->approvalWorkflow->requires($user->organization, 'quality'),
            'requiresMedical' => $this->approvalWorkflow->requires($user->organization, 'medical'),
        ]);
    }

    public function store(Request $request, ImprovementCase $case): RedirectResponse
    {
        abort_if($case->status === 'closed', 422, 'Un plan cerrado no admite nuevas acciones. Debe marcarse como no eficaz para reabrirlo.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'expected_result' => ['nullable', 'string'],
            'required_resources' => ['nullable', 'string'],
            'assignee_type' => ['required', Rule::in(['internal', 'external'])],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
            'external_assignee_name' => ['nullable', 'required_if:assignee_type,external', 'string', 'max:255'],
            'external_assignee_email' => ['nullable', 'email', 'max:255'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'due_at' => ['required', 'date'],
        ]);

        $assigneeIds = collect($data['assignee_ids'] ?? [($data['assigned_to'] ?? null)])->filter()->unique()->values();
        if ($data['assignee_type'] === 'internal') {
            abort_if($assigneeIds->isEmpty(), 422, 'Selecciona al menos un responsable interno.');
            $data['assigned_to'] = $assigneeIds->first();
            $data['external_assignee_name'] = $data['external_assignee_email'] = null;
        } else {
            $data['assigned_to'] = null;
            $assigneeIds = collect();
        }

        unset($data['assignee_ids']);
        $task = Task::create($data + [
            'code' => 'AC-'.now()->format('Y').'-'.Str::upper(Str::random(6)),
            'improvement_case_id' => $case->id,
            'area_id' => $case->reporting_area_id,
            'created_by' => $request->user()->id,
            'status' => 'pending',
        ]);
        $task->assignees()->sync($assigneeIds);
        $this->recordActivity($task, $request->user(), 'created', 'Acción creada y asignada.', [
            'assignee_ids' => $assigneeIds->all(), 'progress' => 0, 'status' => 'pending',
        ]);
        if ($assigneeIds->isNotEmpty()) {
            Notification::send(User::whereIn('id', $assigneeIds)->get(), new TaskAssignedNotification($task, $request->user()));
        }

        return back()->with('status', 'Acción asignada correctamente.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskManagement($request, $task);
        $previous = $task->only(['status', 'progress']);
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'in_review', 'cancelled'])],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'review_notes' => ['nullable', 'string'],
        ]);
        if ($data['status'] === 'in_review') {
            abort_if($task->evidences()->doesntExist(), 422, 'Adjunta al menos una evidencia antes de enviar la acción a revisión.');
            $data['progress'] = 100;
            $data['submitted_at'] = now();
        } else {
            $data += [
                'submitted_at' => null,
                'completed_at' => null,
                'reviewed_by' => null,
                'quality_approved_by' => null,
                'quality_approved_at' => null,
                'medical_approved_by' => null,
                'medical_approved_at' => null,
            ];
        }
        $task->update($data);
        $labels = ['pending' => 'Sin inicio', 'in_progress' => 'En ejecución', 'in_review' => 'En revisión', 'cancelled' => 'Cancelada'];
        $this->recordActivity(
            $task,
            $request->user(),
            $data['status'] === 'in_review' ? 'submitted' : 'progress_updated',
            "Seguimiento actualizado: {$labels[$data['status']]} · {$data['progress']} %.",
            ['before' => $previous, 'after' => ['status' => $data['status'], 'progress' => $data['progress']], 'note' => $data['review_notes'] ?? null],
        );

        return back()->with('status', 'Seguimiento de la acción actualizado.');
    }

    public function review(Request $request, Task $task): RedirectResponse
    {
        abort_unless($task->status === 'in_review', 422, 'La acción debe estar en revisión.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_notes' => ['nullable', 'required_if:decision,reject', 'string', 'max:2000'],
        ]);
        $user = $request->user();
        $approvalType = $this->approvalWorkflow->typeFor($user, $task);
        $isQuality = $approvalType === 'quality';
        $isMedicalDirectorate = $approvalType === 'medical';
        abort_unless($isQuality || $isMedicalDirectorate, 403, 'Solo Calidad o Dirección Médica pueden revisar esta acción.');

        if ($data['decision'] === 'reject') {
            $task->update([
                'status' => 'in_progress',
                'progress' => 90,
                'reviewed_by' => $user->id,
                'review_notes' => $data['review_notes'],
                'submitted_at' => null,
                'completed_at' => null,
                'quality_approved_by' => null,
                'quality_approved_at' => null,
                'medical_approved_by' => null,
                'medical_approved_at' => null,
            ]);
            $assigneeIds = $task->assignees()->pluck('users.id')->push($task->assigned_to)->filter()->unique();
            Notification::send(
                User::whereIn('id', $assigneeIds)->get(),
                new TaskRejectedNotification($task, $user, $data['review_notes']),
            );
            $this->recordActivity($task, $user, 'rejected', 'Acción devuelta al 90 %. Causal: '.$data['review_notes'], [
                'reason' => $data['review_notes'], 'progress' => 90,
            ]);

            return back()->with('status', 'La acción volvió al 90 % y se notificó la causal a sus responsables.');
        }

        $approval = $isQuality
            ? ['quality_approved_by' => $user->id, 'quality_approved_at' => now()]
            : ['medical_approved_by' => $user->id, 'medical_approved_at' => now()];
        $task->update($approval + ['reviewed_by' => $user->id, 'review_notes' => $data['review_notes'] ?? $task->review_notes]);
        $approvalLabel = $isQuality ? 'Calidad' : 'Dirección Médica';
        $this->recordActivity($task, $user, 'approved', "{$approvalLabel} aprobó la acción.", ['approval' => $isQuality ? 'quality' : 'medical']);
        $task->refresh();
        if ($this->approvalWorkflow->canClose($task)) {
            $task->update(['status' => 'completed', 'progress' => 100, 'completed_at' => now()]);
            $this->recordActivity($task, $user, 'completed', 'Acción cerrada con las aprobaciones requeridas.', ['progress' => 100]);
            Notification::send(
                User::where('role', 'quality')->where('is_active', true)->get(),
                new TaskCompletedNotification($task, $user),
            );

            return back()->with('status', 'Acción cerrada con las aprobaciones requeridas.');
        }

        return back()->with('status', 'Aprobación registrada. Falta la aprobación de la otra instancia.');
    }

    public function updateAssignees(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskManagement($request, $task);
        $data = $request->validate([
            'assignee_type' => ['required', Rule::in(['internal', 'external'])],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)->where('organization_id', $request->user()->organization_id))],
            'external_assignee_name' => ['nullable', 'required_if:assignee_type,external', 'string', 'max:255'],
            'external_assignee_email' => ['nullable', 'email', 'max:255'],
        ]);
        $previousAssignees = $task->assignees()->pluck('users.id');
        $assigneeIds = collect($data['assignee_ids'] ?? [])->unique()->values();
        if ($data['assignee_type'] === 'internal') {
            abort_if($assigneeIds->isEmpty(), 422, 'Agrega al menos un responsable CUMPLE.');
            $task->update([
                'assigned_to' => $assigneeIds->first(), 'assignee_type' => 'internal',
                'external_assignee_name' => null, 'external_assignee_email' => null,
            ]);
        } else {
            $assigneeIds = collect();
            $task->update([
                'assigned_to' => null, 'assignee_type' => 'external',
                'external_assignee_name' => $data['external_assignee_name'],
                'external_assignee_email' => $data['external_assignee_email'] ?? null,
            ]);
        }
        $task->assignees()->sync($assigneeIds);
        $newAssignees = $assigneeIds->diff($previousAssignees);
        if ($newAssignees->isNotEmpty()) {
            Notification::send(User::whereIn('id', $newAssignees)->get(), new TaskAssignedNotification($task, $request->user()));
        }
        $names = $data['assignee_type'] === 'external'
            ? $data['external_assignee_name']
            : User::whereIn('id', $assigneeIds)->orderBy('name')->pluck('name')->join(', ');
        $this->recordActivity($task, $request->user(), 'assignees_updated', 'Responsables actualizados: '.$names.'.', [
            'before' => $previousAssignees->all(), 'after' => $assigneeIds->all(), 'type' => $data['assignee_type'],
        ]);

        return back()->with('status', 'Responsables actualizados.');
    }

    public function storeEvidence(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskManagement($request, $task);
        $data = $request->validate(['evidence' => ['required', 'file', 'max:25600'], 'description' => ['nullable', 'string', 'max:1000']]);
        $file = $request->file('evidence');
        $path = $file->store("evidence/{$task->id}");
        $evidence = $task->evidences()->create([
            'uploaded_by' => $request->user()->id, 'description' => $data['description'] ?? null,
            'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
        ]);
        $this->recordActivity($task, $request->user(), 'evidence_added', 'Evidencia adjuntada: '.$evidence->original_name.'.', [
            'evidence_id' => $evidence->id, 'description' => $evidence->description,
        ]);

        return back()->with('status', 'Evidencia adjuntada a la acción.');
    }

    public function storeComment(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskManagement($request, $task);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);
        $this->recordActivity($task, $request->user(), 'comment', $data['body']);

        return back()->with('status', 'Comentario agregado al historial.');
    }

    private function recordActivity(Task $task, User $user, string $type, string $body, array $metadata = []): TaskComment
    {
        return $task->comments()->create([
            'user_id' => $user->id,
            'event_type' => $type,
            'body' => $body,
            'metadata' => $metadata ?: null,
        ]);
    }

    private function authorizeTaskManagement(Request $request, Task $task): void
    {
        $user = $request->user();
        $allowed = in_array($user->role, ['administrator', 'quality'])
            || $this->approvalWorkflow->typeFor($user)
            || $task->assigned_to === $user->id
            || $task->assignees()->whereKey($user->id)->exists()
            || $task->created_by === $user->id
            || ($user->isCoordinator() && $user->area_id === $task->area_id);
        abort_unless($allowed, 403);
    }
}
