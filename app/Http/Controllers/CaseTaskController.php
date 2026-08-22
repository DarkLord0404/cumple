<?php

namespace App\Http\Controllers;

use App\Models\ImprovementCase;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CaseTaskController extends Controller
{
    public function store(Request $request, ImprovementCase $case): RedirectResponse
    {
        abort_if($case->status === 'closed', 422, 'Un plan cerrado no admite nuevas acciones. Debe marcarse como no eficaz para reabrirlo.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'expected_result' => ['nullable', 'string'],
            'required_resources' => ['nullable', 'string'],
            'assignee_type' => ['required', Rule::in(['internal', 'external'])],
            'assigned_to' => ['nullable', 'required_if:assignee_type,internal', 'exists:users,id'],
            'external_assignee_name' => ['nullable', 'required_if:assignee_type,external', 'string', 'max:255'],
            'external_assignee_email' => ['nullable', 'email', 'max:255'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'due_at' => ['required', 'date'],
        ]);

        if ($data['assignee_type'] === 'internal') {
            $data['external_assignee_name'] = $data['external_assignee_email'] = null;
        } else {
            $data['assigned_to'] = null;
        }

        Task::create($data + [
            'code' => 'AC-'.now()->format('Y').'-'.Str::upper(Str::random(6)),
            'improvement_case_id' => $case->id,
            'area_id' => $case->reporting_area_id,
            'created_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        return back()->with('status', 'Acción asignada correctamente.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskManagement($request, $task);
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'in_review', 'completed', 'cancelled'])],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'review_notes' => ['nullable', 'string'],
        ]);
        if ($data['status'] === 'completed') {
            $data['progress'] = 100;
            $data['completed_at'] = now();
        }
        $task->update($data);

        return back()->with('status', 'Seguimiento de la acción actualizado.');
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
            || $task->assigned_to === $user->id
            || $task->created_by === $user->id
            || ($user->role === 'coordinator' && $user->area_id === $task->area_id);
        abort_unless($allowed, 403);
    }
}
