<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\FindingSource;
use App\Models\ImprovementCase;
use App\Models\Task;
use App\Models\User;
use App\Services\InstitutionalFindingSpreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ImprovementCaseController extends Controller
{
    public function index(Request $request): View
    {
        $query = ImprovementCase::query()->with(['source', 'reportingArea'])->withCount('tasks');
        $user = $request->user();
        if (! in_array($user->role, ['administrator', 'quality'])) {
            $query->whereHas('tasks', fn ($tasks) => $tasks->where('assigned_to', $user->id)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($user->id)));
        }
        $availableCases = clone $query;
        $availableCaseIds = (clone $availableCases)->pluck('improvement_cases.id');
        $availableValues = ImprovementCase::whereKey($availableCaseIds);
        $availableTasks = Task::whereIn('improvement_case_id', $availableCaseIds);
        $assigneeIds = (clone $availableTasks)->whereNotNull('assigned_to')->pluck('assigned_to')
            ->merge(DB::table('task_user')->whereIn('task_id', (clone $availableTasks)->pluck('id'))->pluck('user_id'))
            ->unique();
        $query->when($request->filled('q'), function ($query) use ($request): void {
            $search = '%'.$request->string('q')->trim().'%';
            $query->where(fn ($query) => $query->whereLike('code', $search, caseSensitive: false)
                ->orWhereLike('title', $search, caseSensitive: false)->orWhereLike('finding_description', $search, caseSensitive: false));
        });
        $query->when($request->filled('source_id'), fn ($query) => $query->where('finding_source_id', $request->integer('source_id')));
        $query->when($request->filled('action_type'), fn ($query) => $query->where('action_type', $request->string('action_type')));
        $query->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')));
        $query->when($request->filled('area_id'), fn ($query) => $query->where('reporting_area_id', $request->integer('area_id')));
        $query->when($request->filled('assignee_id'), fn ($query) => $query->whereHas('tasks', fn ($tasks) => $tasks
            ->where('assigned_to', $request->integer('assignee_id'))
            ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($request->integer('assignee_id')))));
        $query->when($request->filled('service_area_id'), fn ($query) => $query->whereHas('tasks', fn ($tasks) => $tasks
            ->whereHas('assignee', fn ($assignee) => $assignee->where('area_id', $request->integer('service_area_id')))
            ->orWhereHas('assignees', fn ($assignees) => $assignees->where('area_id', $request->integer('service_area_id')))));

        return view('cases.index', [
            'cases' => $query->latest('reported_at')->paginate(20)->withQueryString(),
            'sources' => FindingSource::whereIn('id', (clone $availableValues)->distinct()->pluck('finding_source_id'))->orderBy('name')->get(),
            'areas' => Area::whereIn('id', (clone $availableValues)->distinct()->pluck('reporting_area_id'))->orderBy('name')->get(),
            'users' => User::whereIn('id', $assigneeIds)->orderBy('name')->get(),
            'serviceAreas' => Area::whereIn('id', User::whereIn('id', $assigneeIds)->whereNotNull('area_id')->distinct()->pluck('area_id'))->orderBy('name')->get(),
            'actionTypes' => (clone $availableValues)->distinct()->pluck('action_type')->filter()->values(),
            'caseStatuses' => (clone $availableValues)->distinct()->pluck('status')->filter()->values(),
        ]);
    }

    public function create(): View
    {
        return view('cases.create', [
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
            'sources' => FindingSource::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function importSpreadsheet(Request $request, InstitutionalFindingSpreadsheet $reader): RedirectResponse
    {
        $request->validate(['excel' => ['required', 'file', 'max:25600', 'mimes:xlsx,xls']]);
        $file = $request->file('excel');
        $temporaryPath = $file->store('temporary-imports');
        try {
            $import = $reader->read(Storage::disk('local')->path($temporaryPath));
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($temporaryPath);
            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            return back()->withErrors(['excel' => 'No fue posible leer el Excel. Verifica que el archivo no esté dañado y conserve la estructura institucional.']);
        }

        $import['finding_source_id'] = $this->matchByName(FindingSource::all(), $import['source_name']);
        $import['reporting_area_id'] = $this->matchByName(Area::all(), $import['reporting_area_name']);
        $import['reported_area_id'] = $this->matchByName(Area::all(), $import['reported_area_name']);
        $import['title'] = $import['institutional_consecutive'] ?: Str::limit($import['finding_description'], 80, '');
        $import['temporary_path'] = $temporaryPath;
        $import['original_name'] = $file->getClientOriginalName();
        $request->session()->put('pending_finding_import', ['path' => $temporaryPath, 'original_name' => $file->getClientOriginalName()]);

        return redirect()->route('cases.create')->with('excel_import', $import);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'finding_source_id' => ['required', 'exists:finding_sources,id'],
            'reporting_area_id' => ['required', 'exists:areas,id'],
            'reported_area_id' => ['nullable', 'exists:areas,id'],
            'reported_at' => ['required', 'date'],
            'action_type' => ['required', Rule::in(['corrective', 'improvement'])],
            'finding_description' => ['required', 'string'],
            'institutional_consecutive' => ['nullable', 'string', 'max:255'],
            'reported_person_name' => ['nullable', 'string', 'max:255'],
            'reported_person_position' => ['nullable', 'string', 'max:255'],
            'urgency_score' => ['nullable', 'integer', 'min:0', 'max:10'],
            'scope_score' => ['nullable', 'integer', 'min:0', 'max:10'],
            'evolution_score' => ['nullable', 'integer', 'min:0', 'max:10'],
            'priority_score' => ['nullable', 'integer', 'min:0', 'max:30'],
            'analysis_method' => ['nullable', Rule::in(['five_whys', 'cause_effect'])],
            'temporary_path' => ['nullable', 'string'],
            'original_name' => ['nullable', 'string', 'max:255'],
        ]);
        $temporaryPath = $data['temporary_path'] ?? null;
        $originalName = $data['original_name'] ?? null;
        $pendingImport = $request->session()->get('pending_finding_import');
        if ($temporaryPath && data_get($pendingImport, 'path') !== $temporaryPath) {
            return back()->withErrors(['excel' => 'La importación venció o no corresponde a esta sesión. Vuelve a cargar el Excel.'])->withInput();
        }
        unset($data['temporary_path'], $data['original_name']);
        $data += ['reported_by' => $request->user()->id, 'status' => ($data['analysis_method'] ?? null) ? 'analysis' : 'draft'];
        $case = ImprovementCase::create($data + ['code' => 'H-'.now()->format('Y').'-'.Str::upper(Str::random(6))]);

        if ($temporaryPath && str_starts_with($temporaryPath, 'temporary-imports/') && Storage::disk('local')->exists($temporaryPath)) {
            $finalPath = "official-documents/{$case->id}/".basename($temporaryPath);
            Storage::disk('local')->move($temporaryPath, $finalPath);
            $case->documents()->create([
                'uploaded_by' => $request->user()->id, 'document_stage' => 'original', 'document_type' => 'finding_report',
                'disk' => 'local', 'path' => $finalPath, 'original_name' => $originalName ?: basename($finalPath),
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'size' => Storage::disk('local')->size($finalPath),
                'notes' => 'Documento institucional importado automáticamente.',
            ]);
            $request->session()->forget('pending_finding_import');
        }

        return redirect()->route('cases.show', $case)->with('status', 'Hallazgo registrado. Ya puedes crear sus acciones.');
    }

    public function show(ImprovementCase $case): View
    {
        return view('cases.show', [
            'case' => $case->load(['source', 'reportingArea', 'reportedArea', 'reporter', 'documents.uploader', 'tasks.assignee', 'tasks.assignees', 'tasks.evidences.uploader', 'tasks.qualityApprover', 'tasks.medicalApprover']),
            'users' => User::where('is_active', true)->with('area')->orderBy('name')->get(),
            'canReviewAsQuality' => auth()->user()->role === 'quality',
            'canReviewAsMedicalDirectorate' => auth()->user()->role === 'coordinator_medical' && (
                auth()->user()->area()->where('slug', 'direccion-medica')->exists()
                || Area::where('slug', 'direccion-medica')->where('coordinator_id', auth()->id())->exists()
            ),
        ]);
    }

    private function matchByName($models, ?string $name): ?int
    {
        if (! $name) {
            return null;
        }
        $needle = Str::lower(Str::ascii(trim($name)));

        return $models->first(fn ($model) => Str::lower(Str::ascii(trim($model->name))) === $needle)?->id;
    }
}
