<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\MeetingMinute;
use App\Models\MinuteDocumentVersion;
use App\Models\MinuteCommitmentProposal;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Services\InstitutionalMinuteDocument;
use App\Services\KairoMinuteVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeetingMinuteController extends Controller
{
    public function __construct(private readonly KairoMinuteVisibility $visibility) {}

    public function index(Request $request): View
    {
        $query = MeetingMinute::with(['area', 'tasks'])->latest('held_at');
        return view('minutes.index', ['minutes' => $this->visibility->apply($query, $request->user())->paginate(20)]);
    }

    public function create(): View
    {
        return view('minutes.create', ['areas' => Area::where('is_active', true)->orderBy('name')->get(), 'users' => User::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'held_at' => ['required', 'date'],
            'area_id' => ['required', Rule::exists('areas', 'id')->where('organization_id', $request->user()->organization_id)], 'organizer' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'], 'objective' => ['nullable', 'string'],
            'agenda' => ['nullable', 'string'], 'development' => ['nullable', 'string'], 'decisions' => ['nullable', 'string'],
            'attendees' => ['nullable', 'array'], 'attendees.*' => [Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
            'external_participant_names' => ['nullable', 'string'],
        ]);
        $external = collect(preg_split('/\r\n|\r|\n/', $data['external_participant_names'] ?? ''))->filter()->map(fn ($name) => ['name' => trim($name)])->values()->all();
        $minute = MeetingMinute::create(collect($data)->except(['attendees', 'external_participant_names'])->all() + [
            'number' => now()->format('Y').'-'.Str::upper(Str::random(5)), 'created_by' => $request->user()->id,
            'meeting_type' => 'institutional', 'status' => 'draft', 'external_participants' => $external,
        ]);
        $minute->attendees()->sync($data['attendees'] ?? []);

        return redirect()->route('minutes.show', $minute)->with('status', 'Borrador de acta creado.');
    }

    public function show(MeetingMinute $minute): View
    {
        $this->authorizeVisibility($minute);
        $users = User::where('is_active', true)->orderBy('name')->get();
        $minute->load(['area', 'attendees', 'tasks.assignee', 'tasks.assignees', 'documentVersions.generator', 'commitmentProposals.task']);
        $suggestedUsers = $minute->commitmentProposals->mapWithKeys(function (MinuteCommitmentProposal $proposal) use ($users): array {
            $suggested = Str::lower(Str::ascii(trim((string) $proposal->suggested_responsible)));
            $match = $suggested === '' ? null : $users->first(fn (User $user) => Str::lower(Str::ascii(trim($user->name))) === $suggested);
            return [$proposal->id => $match?->id];
        });

        $areas = Area::where('is_active', true)->orderBy('name')->get();
        return view('minutes.show', compact('minute', 'users', 'areas', 'suggestedUsers'));
    }

    public function edit(MeetingMinute $minute): View
    {
        $this->authorizeVisibility($minute);
        return view('minutes.edit', [
            'minute' => $minute->load('attendees'),
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, MeetingMinute $minute): RedirectResponse
    {
        $this->authorizeVisibility($minute);
        $data = $this->validatedMinute($request);
        $external = collect(preg_split('/\r\n|\r|\n/', $data['external_participant_names'] ?? ''))->filter()->map(fn ($name) => ['name' => trim($name)])->values()->all();
        $minute->update(collect($data)->except(['attendees', 'external_participant_names'])->all() + [
            'external_participants' => $external, 'status' => 'draft',
        ]);
        $minute->attendees()->sync($data['attendees'] ?? []);

        return redirect()->route('minutes.show', $minute)->with('status', 'Borrador actualizado. Genera una nueva versión Word cuando esté listo.');
    }

    public function addCommitment(Request $request, MeetingMinute $minute): RedirectResponse
    {
        $this->authorizeVisibility($minute);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'assignee_type' => ['required', Rule::in(['internal', 'external'])],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)], 'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
            'external_assignee_name' => ['nullable', 'required_if:assignee_type,external', 'string', 'max:255'],
            'due_at' => ['required', 'date'], 'expected_result' => ['nullable', 'string'],
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('organization_id', $request->user()->organization_id)],
        ]);
        $areaId = $data['area_id'] ?? $minute->area_id;
        abort_unless($areaId, 422, 'Selecciona el área responsable del compromiso.');
        $assigneeIds = collect($data['assignee_ids'] ?? [($data['assigned_to'] ?? null)])->filter()->unique()->values();
        if ($data['assignee_type'] === 'external') {
            $data['assigned_to'] = null;
        } else {
            abort_if($assigneeIds->isEmpty(), 422, 'Selecciona al menos un responsable interno.');
            $data['assigned_to'] = $assigneeIds->first();
        }
        unset($data['assignee_ids']);
        $task = Task::create($data + ['code' => 'AC-'.now()->format('Y').'-'.Str::upper(Str::random(6)), 'area_id' => $areaId, 'meeting_minute_id' => $minute->id, 'created_by' => $request->user()->id, 'priority' => 'medium', 'status' => 'pending']);
        $task->assignees()->sync($assigneeIds);
        if ($assigneeIds->isNotEmpty()) {
            Notification::send(User::whereIn('id', $assigneeIds)->get(), new TaskAssignedNotification($task, $request->user()));
        }

        return back()->with('status', 'Compromiso agregado y asignado como acción.');
    }

    public function convertProposal(Request $request, MeetingMinute $minute, MinuteCommitmentProposal $proposal): RedirectResponse
    {
        $this->authorizeVisibility($minute);
        abort_unless($proposal->meeting_minute_id === $minute->id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'assignee_type' => ['required', Rule::in(['internal', 'external'])],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
            'external_assignee_name' => ['nullable', 'required_if:assignee_type,external', 'string', 'max:255'],
            'due_at' => ['required', 'date'],
            'expected_result' => ['nullable', 'string'],
            'area_id' => ['required', Rule::exists('areas', 'id')->where('organization_id', $request->user()->organization_id)],
        ]);
        $assigneeIds = collect($data['assignee_ids'] ?? [])->filter()->unique()->values();
        if ($data['assignee_type'] === 'internal') {
            abort_if($assigneeIds->isEmpty(), 422, 'Selecciona al menos un responsable interno.');
            $data['assigned_to'] = $assigneeIds->first();
            $data['external_assignee_name'] = null;
        } else {
            $data['assigned_to'] = null;
            $assigneeIds = collect();
        }
        unset($data['assignee_ids']);

        $task = DB::transaction(function () use ($proposal, $minute, $data, $assigneeIds, $request): Task {
            $locked = MinuteCommitmentProposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== 'pending', 422, 'Esta propuesta ya fue procesada.');
            $task = Task::create($data + [
                'code' => 'AC-'.now()->format('Y').'-'.Str::upper(Str::random(6)),
                'area_id' => $data['area_id'], 'meeting_minute_id' => $minute->id,
                'created_by' => $request->user()->id, 'priority' => 'medium', 'status' => 'pending',
            ]);
            $task->assignees()->sync($assigneeIds);
            $locked->update(['status' => 'converted', 'task_id' => $task->id]);
            $minute->update(['status' => 'draft']);
            return $task;
        });
        if ($assigneeIds->isNotEmpty()) {
            Notification::send(User::whereIn('id', $assigneeIds)->get(), new TaskAssignedNotification($task, $request->user()));
        }

        return back()->with('status', 'Compromiso convertido en tarea y asignado correctamente.');
    }

    public function dismissProposal(MeetingMinute $minute, MinuteCommitmentProposal $proposal): RedirectResponse
    {
        $this->authorizeVisibility($minute);
        abort_unless($proposal->meeting_minute_id === $minute->id, 404);
        abort_if($proposal->status !== 'pending', 422, 'Esta propuesta ya fue procesada.');
        $proposal->update(['status' => 'dismissed']);

        return back()->with('status', 'Propuesta descartada. No se creó ninguna tarea.');
    }

    public function updateCommitment(Request $request, MeetingMinute $minute, Task $task): RedirectResponse
    {
        $this->authorizeVisibility($minute);
        abort_unless($task->meeting_minute_id === $minute->id, 404);
        abort_if($task->status === 'completed', 422, 'Un compromiso cerrado no puede modificarse.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'expected_result' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'assignee_type' => ['required', Rule::in(['internal', 'external'])],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
            'external_assignee_name' => ['nullable', 'required_if:assignee_type,external', 'string', 'max:255'],
        ]);
        $assigneeIds = collect($data['assignee_ids'] ?? [])->filter()->unique()->values();
        if ($data['assignee_type'] === 'internal') {
            abort_if($assigneeIds->isEmpty(), 422, 'Selecciona al menos un responsable interno.');
            $data['assigned_to'] = $assigneeIds->first();
            $data['external_assignee_name'] = null;
        } else {
            $data['assigned_to'] = null;
            $assigneeIds = collect();
        }
        unset($data['assignee_ids']);
        $task->update($data);
        $task->assignees()->sync($assigneeIds);

        return back()->with('status', 'Compromiso actualizado. La próxima versión Word incluirá los cambios.');
    }

    public function destroyCommitment(MeetingMinute $minute, Task $task): RedirectResponse
    {
        $this->authorizeVisibility($minute);
        abort_unless($task->meeting_minute_id === $minute->id, 404);
        abort_if(in_array($task->status, ['in_review', 'completed'], true), 422, 'No se puede eliminar un compromiso enviado a revisión o cerrado.');
        $task->delete();

        return back()->with('status', 'Compromiso eliminado del borrador.');
    }

    public function generate(MeetingMinute $minute, InstitutionalMinuteDocument $documents): RedirectResponse
    {
        $this->authorizeVisibility($minute);
        $version = ($minute->documentVersions()->max('version') ?? 0) + 1;
        $path = $documents->generate($minute, $version);
        $minute->documentVersions()->create([
            'organization_id' => $minute->organization_id ?? auth()->user()->organization_id ?? Organization::query()->value('id'), 'version' => $version,
            'disk' => 'local', 'path' => $path, 'original_name' => "acta-{$minute->number}-v{$version}.docx",
            'generated_by' => auth()->id(),
        ]);
        $minute->update(['generated_document_path' => $path, 'status' => 'ready']);

        return back()->with('status', "Versión {$version} del acta Word generada correctamente.");
    }

    public function download(MeetingMinute $minute): StreamedResponse
    {
        $this->authorizeVisibility($minute);
        abort_unless($minute->generated_document_path && Storage::disk('local')->exists($minute->generated_document_path), 404);

        return Storage::disk('local')->download($minute->generated_document_path, "acta-{$minute->number}.docx");
    }

    public function downloadVersion(MeetingMinute $minute, MinuteDocumentVersion $version): StreamedResponse
    {
        $this->authorizeVisibility($minute);
        abort_unless($version->meeting_minute_id === $minute->id && Storage::disk($version->disk)->exists($version->path), 404);

        return Storage::disk($version->disk)->download($version->path, $version->original_name);
    }

    private function validatedMinute(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'], 'held_at' => ['required', 'date'],
            'area_id' => ['required', Rule::exists('areas', 'id')->where('organization_id', $request->user()->organization_id)], 'organizer' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'], 'objective' => ['nullable', 'string'],
            'agenda' => ['nullable', 'string'], 'development' => ['nullable', 'string'], 'decisions' => ['nullable', 'string'],
            'attendees' => ['nullable', 'array'], 'attendees.*' => [Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
            'external_participant_names' => ['nullable', 'string'],
        ]);
    }

    private function authorizeVisibility(MeetingMinute $minute): void
    {
        abort_unless($this->visibility->canView($minute, request()->user()), 403);
    }
}
