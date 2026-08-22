<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\ImprovementCase;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCompletedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CaseTaskController extends Controller
{
    public function show(Request $request, Task $task): View
    {
        $this->authorizeTaskManagement($request, $task);
        $user = $request->user();

        return view('tasks.show', [
            'task' => $task->load(['improvementCase', 'minute', 'area', 'assignees', 'evidences.uploader', 'qualityApprover', 'medicalApprover']),
            'users' => User::where('is_active', true)->with('area')->orderBy('name')->get(),
            'canReviewAsQuality' => $user->role === 'quality',
            'canReviewAsMedicalDirectorate' => $this->isMedicalDirectorateApprover($user),
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
            'assigned_to' => ['nullable', 'exists:users,id'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'distinct', 'exists:users,id'],
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
        if ($assigneeIds->isNotEmpty()) {
            Notification::send(User::whereIn('id', $assigneeIds)->get(), new TaskAssignedNotification($task, $request->user()));
        }

        return back()->with('status', 'Acción asignada correctamente.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskManagement($request, $task);
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'in_review', 'cancelled'])],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'review_notes' => ['nullable', 'string'],
        ]);
        if ($data['status'] === 'in_review') {
            abort_if($task->evidences()->doesntExist(), 422, 'Adjunta al menos una evidencia antes de enviar la acción a revisión.');
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
        $isQuality = $user->role === 'quality';
        $isMedicalDirectorate = $this->isMedicalDirectorateApprover($user);
        abort_unless($isQuality || $isMedicalDirectorate, 403, 'Solo Calidad o Dirección Médica pueden revisar esta acción.');

        if ($data['decision'] === 'reject') {
            $task->update([
                'status' => 'in_progress',
                'reviewed_by' => $user->id,
                'review_notes' => $data['review_notes'],
                'submitted_at' => null,
                'completed_at' => null,
                'quality_approved_by' => null,
                'quality_approved_at' => null,
                'medical_approved_by' => null,
                'medical_approved_at' => null,
            ]);

            return back()->with('status', 'La acción fue devuelta al responsable con observaciones.');
        }

        $approval = $isQuality
            ? ['quality_approved_by' => $user->id, 'quality_approved_at' => now()]
            : ['medical_approved_by' => $user->id, 'medical_approved_at' => now()];
        $task->update($approval + ['reviewed_by' => $user->id, 'review_notes' => $data['review_notes'] ?? $task->review_notes]);
        $task->refresh();
        if ($task->quality_approved_at && $task->medical_approved_at) {
            $task->update(['status' => 'completed', 'progress' => 100, 'completed_at' => now()]);
            Notification::send(
                User::where('role', 'quality')->where('is_active', true)->get(),
                new TaskCompletedNotification($task, $user),
            );

            return back()->with('status', 'Acción cerrada con aprobación de Calidad y Dirección Médica.');
        }

        return back()->with('status', 'Aprobación registrada. Falta la aprobación de la otra instancia.');
    }

    public function updateAssignees(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskManagement($request, $task);
        $data = $request->validate([
            'assignee_ids' => ['required', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('is_active', true)],
        ]);
        $previousAssignees = $task->assignees()->pluck('users.id');
        $task->assignees()->sync($data['assignee_ids']);
        $task->update(['assigned_to' => $data['assignee_ids'][0], 'assignee_type' => 'internal']);
        $newAssignees = collect($data['assignee_ids'])->diff($previousAssignees);
        if ($newAssignees->isNotEmpty()) {
            Notification::send(User::whereIn('id', $newAssignees)->get(), new TaskAssignedNotification($task, $request->user()));
        }

        return back()->with('status', 'Responsables actualizados.');
    }

    public function storeEvidence(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskManagement($request, $task);
        $data = $request->validate(['evidence' => ['required', 'file', 'max:25600'], 'description' => ['nullable', 'string', 'max:1000']]);
        $file = $request->file('evidence');
        $path = $file->store("evidence/{$task->id}");
        $task->evidences()->create([
            'uploaded_by' => $request->user()->id, 'description' => $data['description'] ?? null,
            'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
        ]);

        return back()->with('status', 'Evidencia adjuntada a la acción.');
    }

    private function authorizeTaskManagement(Request $request, Task $task): void
    {
        $user = $request->user();
        $allowed = in_array($user->role, ['administrator', 'quality'])
            || $this->isMedicalDirectorateApprover($user)
            || $task->assigned_to === $user->id
            || $task->assignees()->whereKey($user->id)->exists()
            || $task->created_by === $user->id
            || ($user->isCoordinator() && $user->area_id === $task->area_id);
        abort_unless($allowed, 403);
    }

    private function isMedicalDirectorateApprover(User $user): bool
    {
        return $user->role === 'coordinator_medical' && (
            $user->area()->where('slug', 'direccion-medica')->exists()
            || Area::where('slug', 'direccion-medica')->where('coordinator_id', $user->id)->exists()
        );
    }
}
