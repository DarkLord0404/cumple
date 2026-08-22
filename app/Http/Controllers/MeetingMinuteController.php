<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\MeetingMinute;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Services\InstitutionalMinuteDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeetingMinuteController extends Controller
{
    public function index(): View
    {
        return view('minutes.index', ['minutes' => MeetingMinute::with(['area', 'tasks'])->latest('held_at')->paginate(20)]);
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
        return view('minutes.show', ['minute' => $minute->load(['area', 'attendees', 'tasks.assignee', 'tasks.assignees']), 'users' => User::where('is_active', true)->orderBy('name')->get()]);
    }

    public function addCommitment(Request $request, MeetingMinute $minute): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'assignee_type' => ['required', Rule::in(['internal', 'external'])],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)], 'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
            'external_assignee_name' => ['nullable', 'required_if:assignee_type,external', 'string', 'max:255'],
            'due_at' => ['required', 'date'], 'expected_result' => ['nullable', 'string'],
        ]);
        $assigneeIds = collect($data['assignee_ids'] ?? [($data['assigned_to'] ?? null)])->filter()->unique()->values();
        if ($data['assignee_type'] === 'external') {
            $data['assigned_to'] = null;
        } else {
            abort_if($assigneeIds->isEmpty(), 422, 'Selecciona al menos un responsable interno.');
            $data['assigned_to'] = $assigneeIds->first();
        }
        unset($data['assignee_ids']);
        $task = Task::create($data + ['code' => 'AC-'.now()->format('Y').'-'.Str::upper(Str::random(6)), 'area_id' => $minute->area_id, 'meeting_minute_id' => $minute->id, 'created_by' => $request->user()->id, 'priority' => 'medium', 'status' => 'pending']);
        $task->assignees()->sync($assigneeIds);
        if ($assigneeIds->isNotEmpty()) {
            Notification::send(User::whereIn('id', $assigneeIds)->get(), new TaskAssignedNotification($task, $request->user()));
        }

        return back()->with('status', 'Compromiso agregado y asignado como acción.');
    }

    public function generate(MeetingMinute $minute, InstitutionalMinuteDocument $documents): RedirectResponse
    {
        $minute->update(['generated_document_path' => $documents->generate($minute), 'status' => 'ready']);

        return back()->with('status', 'Acta Word generada sobre la plantilla institucional.');
    }

    public function download(MeetingMinute $minute): StreamedResponse
    {
        abort_unless($minute->generated_document_path && Storage::disk('local')->exists($minute->generated_document_path), 404);

        return Storage::disk('local')->download($minute->generated_document_path, "acta-{$minute->number}.docx");
    }
}
