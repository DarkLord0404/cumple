<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\FindingSource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdministrationCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdministrator($request);

        return view('administration.index', [
            'organization' => $request->user()->organization,
            'areaCount' => Area::count(),
            'sourceCount' => FindingSource::count(),
        ]);
    }

    public function organization(Request $request): View
    {
        $this->authorizeAdministrator($request);

        return view('administration.organization', [
            'organization' => $request->user()->organization,
        ]);
    }

    public function areas(Request $request): View
    {
        $this->authorizeAdministrator($request);

        return view('administration.areas', [
            'areas' => Area::with(['coordinator'])->orderBy('name')->get(),
            'coordinators' => User::with('area')->where('is_active', true)->whereIn('role', User::COORDINATOR_ROLES)->orderBy('name')->get(),
        ]);
    }

    public function sources(Request $request): View
    {
        $this->authorizeAdministrator($request);

        return view('administration.sources', [
            'sources' => FindingSource::orderBy('name')->get(),
        ]);
    }

    public function reminders(Request $request): View
    {
        $this->authorizeAdministrator($request);

        return view('administration.reminders', [
            'organization' => $request->user()->organization,
        ]);
    }

    public function approvals(Request $request): View
    {
        $this->authorizeAdministrator($request);
        $organization = $request->user()->organization;

        return view('administration.approvals', [
            'organization' => $organization,
            'users' => User::where('is_active', true)->with('area')->orderBy('name')->get(),
            'qualityApproverIds' => DB::table('organization_approvers')->where('organization_id', $organization->id)->where('approval_type', 'quality')->pluck('user_id'),
            'medicalApproverIds' => DB::table('organization_approvers')->where('organization_id', $organization->id)->where('approval_type', 'medical')->pluck('user_id'),
        ]);
    }

    public function kairo(Request $request): View
    {
        $this->authorizeAdministrator($request);
        $organization = $request->user()->organization;

        return view('administration.kairo', [
            'organization' => $organization,
            'users' => User::where('is_active', true)->orderBy('name')->get(),
            'viewerIds' => DB::table('organization_kairo_minute_viewers')->where('organization_id', $organization->id)->pluck('user_id'),
        ]);
    }

    public function updateKairo(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $organization = $request->user()->organization;
        $data = $request->validate([
            'kairo_minute_visibility' => ['required', Rule::in(['administrators', 'selected', 'everyone'])],
            'viewer_ids' => ['nullable', 'array'],
            'viewer_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('organization_id', $organization->id)],
        ]);
        if ($data['kairo_minute_visibility'] === 'selected' && empty($data['viewer_ids'])) {
            return back()->withErrors(['viewer_ids' => 'Selecciona al menos un usuario autorizado.'])->withInput();
        }
        DB::transaction(function () use ($organization, $data): void {
            $organization->update(['kairo_minute_visibility' => $data['kairo_minute_visibility']]);
            DB::table('organization_kairo_minute_viewers')->where('organization_id', $organization->id)->delete();
            foreach ($data['viewer_ids'] ?? [] as $userId) {
                DB::table('organization_kairo_minute_viewers')->insert([
                    'organization_id' => $organization->id, 'user_id' => $userId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        return back()->with('status', 'Visibilidad de las actas de Kairo actualizada.');
    }

    public function updateApprovals(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $organization = $request->user()->organization;
        $data = $request->validate([
            'approval_policy' => ['required', Rule::in(['both', 'quality', 'medical'])],
            'quality_approver_ids' => ['nullable', 'array'],
            'quality_approver_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('organization_id', $organization->id)],
            'medical_approver_ids' => ['nullable', 'array'],
            'medical_approver_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('organization_id', $organization->id)],
        ]);
        if (in_array($data['approval_policy'], ['both', 'quality'], true) && empty($data['quality_approver_ids'])) {
            return back()->withErrors(['quality_approver_ids' => 'Selecciona al menos un aprobador de Calidad.'])->withInput();
        }
        if (in_array($data['approval_policy'], ['both', 'medical'], true) && empty($data['medical_approver_ids'])) {
            return back()->withErrors(['medical_approver_ids' => 'Selecciona al menos un aprobador de Dirección Médica.'])->withInput();
        }
        DB::transaction(function () use ($organization, $data): void {
            $organization->update(['approval_policy' => $data['approval_policy']]);
            DB::table('organization_approvers')->where('organization_id', $organization->id)->delete();
            foreach (['quality', 'medical'] as $type) {
                foreach ($data["{$type}_approver_ids"] ?? [] as $userId) {
                    DB::table('organization_approvers')->insert([
                        'organization_id' => $organization->id, 'user_id' => $userId,
                        'approval_type' => $type, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });

        return back()->with('status', 'Roles de aprobación actualizados.');
    }

    public function updateReminders(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'reminders_enabled' => ['required', 'boolean'],
            'reminder_days' => ['nullable', 'array'],
            'reminder_days.*' => ['integer', Rule::in([1, 3, 7, 14, 30])],
            'overdue_alerts_enabled' => ['required', 'boolean'],
            'review_alerts_enabled' => ['required', 'boolean'],
        ]);
        $data['reminder_days'] = collect($data['reminder_days'] ?? [7, 3, 1])->map(fn ($day) => (int) $day)->unique()->sortDesc()->values()->all();
        $request->user()->organization()->update($data);

        return back()->with('status', 'Recordatorios y alertas actualizados.');
    }

    public function updateOrganization(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Escribe el nombre de la organización.',
        ]);

        $request->user()->organization()->update([
            'name' => Str::squish($data['name']),
        ]);

        return back()->with('status', 'Nombre de la organización actualizado.');
    }

    public function storeArea(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('areas', 'name')->where('organization_id', $request->user()->organization_id)],
            'description' => ['nullable', 'string'],
            'coordinator_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->where('organization_id', $request->user()->organization_id)
                    ->whereIn('role', User::COORDINATOR_ROLES)),
            ],
        ]);
        $slug = Str::slug($data['name']);
        abort_if(Area::where('slug', $slug)->exists(), 422, 'Ya existe un área con un nombre equivalente.');
        DB::transaction(function () use ($data, $slug): void {
            $area = Area::create($data + ['slug' => $slug, 'is_active' => true]);
            $this->syncAreaCoordinator($area, null, $data['coordinator_id'] ?? null);
        });

        return back()->with('status', 'Área creada correctamente.');
    }

    public function updateArea(Request $request, Area $area): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('areas')->where('organization_id', $request->user()->organization_id)->ignore($area)],
            'description' => ['nullable', 'string'],
            'coordinator_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->where('organization_id', $request->user()->organization_id)
                    ->whereIn('role', User::COORDINATOR_ROLES)),
            ],
            'is_active' => ['required', 'boolean'],
        ]);
        $slug = Str::slug($data['name']);
        abort_if(Area::where('slug', $slug)->whereKeyNot($area->getKey())->exists(), 422, 'Ya existe un área con un nombre equivalente.');
        $previousCoordinatorId = $area->coordinator_id;
        DB::transaction(function () use ($area, $data, $slug, $previousCoordinatorId): void {
            $area->update($data + ['slug' => $slug]);
            $this->syncAreaCoordinator($area, $previousCoordinatorId, $data['coordinator_id'] ?? null);
        });

        return back()->with('status', 'Área actualizada.');
    }

    public function destroyArea(Request $request, Area $area): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $dependencies = DB::table('tasks')->where('area_id', $area->id)->exists()
            || DB::table('improvement_cases')->where('reporting_area_id', $area->id)->exists();

        if ($dependencies) {
            return back()->withErrors(['area' => 'No se puede eliminar esta área porque tiene tareas o hallazgos asociados. Puedes marcarla como inactiva.']);
        }

        DB::transaction(function () use ($area): void {
            User::where('area_id', $area->id)->update(['area_id' => null]);
            $area->delete();
        });

        return back()->with('status', 'Área eliminada correctamente.');
    }

    public function storeSource(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('finding_sources', 'name')->where('organization_id', $request->user()->organization_id)],
            'is_invima' => ['required', 'boolean'],
        ]);
        FindingSource::create($data + ['is_active' => true]);

        return back()->with('status', 'Fuente creada correctamente.');
    }

    public function updateSource(Request $request, FindingSource $source): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('finding_sources')->where('organization_id', $request->user()->organization_id)->ignore($source)],
            'is_invima' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'],
        ]);
        $source->update($data);

        return back()->with('status', 'Fuente actualizada.');
    }

    private function authorizeAdministrator(Request $request): void
    {
        abort_unless($request->user()->role === 'administrator', 403);
    }

    private function syncAreaCoordinator(Area $area, ?int $previousCoordinatorId, ?int $coordinatorId): void
    {
        if ($previousCoordinatorId && $previousCoordinatorId !== $coordinatorId) {
            User::whereKey($previousCoordinatorId)->where('area_id', $area->id)->update(['area_id' => null]);
        }

        if ($coordinatorId) {
            Area::where('coordinator_id', $coordinatorId)->whereKeyNot($area->id)->update(['coordinator_id' => null]);
            User::whereKey($coordinatorId)->update(['area_id' => $area->id]);
        }
    }
}
