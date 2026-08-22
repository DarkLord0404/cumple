<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\FindingSource;
use App\Models\ImprovementCase;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ImprovementCaseController extends Controller
{
    public function index(): View
    {
        return view('cases.index', ['cases' => ImprovementCase::with(['source', 'reportingArea', 'tasks'])->latest('reported_at')->paginate(20)]);
    }

    public function create(): View
    {
        return view('cases.create', [
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
            'sources' => FindingSource::where('is_active', true)->orderBy('name')->get(),
        ]);
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
        ]);
        $data += ['reported_by' => $request->user()->id, 'status' => 'draft'];
        $case = ImprovementCase::create($data + ['code' => 'H-'.now()->format('Y').'-'.Str::upper(Str::random(6))]);

        return redirect()->route('cases.show', $case)->with('status', 'Hallazgo registrado. Ya puedes crear sus acciones.');
    }

    public function show(ImprovementCase $case): View
    {
        return view('cases.show', [
            'case' => $case->load(['source', 'reportingArea', 'reportedArea', 'reporter', 'tasks.assignee']),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
