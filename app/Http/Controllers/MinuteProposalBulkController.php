<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\MeetingMinute;
use App\Models\MinuteCommitmentProposal;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Services\KairoMinuteVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MinuteProposalBulkController extends Controller
{
    public function __construct(private readonly KairoMinuteVisibility $visibility) {}

    public function edit(Request $request, MeetingMinute $minute): View
    {
        $this->authorizeMinute($request, $minute);
        abort_unless($minute->source_system === 'kairo', 404);

        return view('minutes.proposals-bulk-edit', [
            'minute' => $minute->load(['commitmentProposals' => fn ($query) => $query->where('status', 'pending')->orderBy('id')]),
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function process(Request $request, MeetingMinute $minute): RedirectResponse
    {
        $this->authorizeMinute($request, $minute);
        $data = $request->validate([
            'action' => ['required', Rule::in(['convert', 'dismiss'])],
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['integer', 'distinct'],
            'proposals' => ['required_if:action,convert', 'array'],
            'proposals.*.title' => ['nullable', 'string', 'max:255'],
            'proposals.*.area_id' => ['nullable', Rule::exists('areas', 'id')->where('organization_id', $request->user()->organization_id)],
            'proposals.*.due_at' => ['nullable', 'date'],
            'proposals.*.expected_result' => ['nullable', 'string'],
            'proposals.*.assignee_type' => ['nullable', Rule::in(['internal', 'external'])],
            'proposals.*.assignee_ids' => ['nullable', 'array'],
            'proposals.*.assignee_ids.*' => ['integer', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
            'proposals.*.external_assignee_name' => ['nullable', 'string', 'max:255'],
        ]);
        $selected = collect($data['selected'])->map(fn ($id) => (int) $id)->unique();
        $proposals = $minute->commitmentProposals()->where('status', 'pending')->whereIn('id', $selected)->get()->keyBy('id');
        abort_unless($proposals->count() === $selected->count(), 404);

        if ($data['action'] === 'dismiss') {
            $proposals->each->update(['status' => 'dismissed']);
            return redirect()->route('minutes.show', $minute)->with('status', $proposals->count().' propuestas eliminadas.');
        }

        $created = [];
        DB::transaction(function () use ($selected, $data, $minute, $request, &$created): void {
            foreach ($selected as $proposalId) {
                $proposal = MinuteCommitmentProposal::whereKey($proposalId)->lockForUpdate()->firstOrFail();
                abort_if($proposal->status !== 'pending', 422, 'Una de las propuestas ya fue procesada.');
                $values = $data['proposals'][$proposalId] ?? [];
                abort_if(blank($values['title'] ?? null) || blank($values['area_id'] ?? null) || blank($values['due_at'] ?? null) || blank($values['assignee_type'] ?? null), 422, 'Completa tarea, área, fecha y responsable en todas las filas seleccionadas.');
                $assigneeIds = collect($values['assignee_ids'] ?? [])->filter()->unique()->values();
                if ($values['assignee_type'] === 'internal') {
                    abort_if($assigneeIds->isEmpty(), 422, 'Selecciona responsables CUMPLE en todas las tareas internas.');
                    $values['assigned_to'] = $assigneeIds->first();
                    $values['external_assignee_name'] = null;
                } else {
                    abort_if(blank($values['external_assignee_name'] ?? null), 422, 'Completa el nombre de cada responsable externo.');
                    $values['assigned_to'] = null;
                    $assigneeIds = collect();
                }
                unset($values['assignee_ids']);
                $task = Task::create($values + [
                    'code' => 'AC-'.now()->format('Y').'-'.Str::upper(Str::random(6)),
                    'meeting_minute_id' => $minute->id, 'created_by' => $request->user()->id,
                    'priority' => 'medium', 'status' => 'pending',
                ]);
                $task->assignees()->sync($assigneeIds);
                $proposal->update(['status' => 'converted', 'task_id' => $task->id]);
                $created[] = [$task, $assigneeIds];
            }
            $minute->update(['status' => 'draft']);
        });
        foreach ($created as [$task, $assigneeIds]) {
            if ($assigneeIds->isNotEmpty()) {
                Notification::send(User::whereIn('id', $assigneeIds)->get(), new TaskAssignedNotification($task, $request->user()));
            }
        }

        return redirect()->route('minutes.show', $minute)->with('status', count($created).' tareas creadas y asignadas.');
    }

    private function authorizeMinute(Request $request, MeetingMinute $minute): void
    {
        abort_unless($this->visibility->canView($minute, $request->user()), 403);
    }
}
