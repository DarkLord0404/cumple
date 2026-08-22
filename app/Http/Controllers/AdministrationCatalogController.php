<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\FindingSource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdministrationCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdministrator($request);

        return view('administration.catalogs', [
            'areas' => Area::with(['coordinator'])->orderBy('name')->get(),
            'sources' => FindingSource::orderBy('name')->get(),
            'coordinators' => User::where('is_active', true)->whereIn('role', ['administrator', 'coordinator'])->orderBy('name')->get(),
        ]);
    }

    public function storeArea(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:areas,name'],
            'description' => ['nullable', 'string'],
            'coordinator_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereIn('role', ['administrator', 'coordinator'])),
            ],
        ]);
        $slug = Str::slug($data['name']);
        abort_if(Area::where('slug', $slug)->exists(), 422, 'Ya existe un área con un nombre equivalente.');
        Area::create($data + ['slug' => $slug, 'is_active' => true]);

        return back()->with('status', 'Área creada correctamente.');
    }

    public function updateArea(Request $request, Area $area): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('areas')->ignore($area)],
            'description' => ['nullable', 'string'],
            'coordinator_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereIn('role', ['administrator', 'coordinator'])),
            ],
            'is_active' => ['required', 'boolean'],
        ]);
        $slug = Str::slug($data['name']);
        abort_if(Area::where('slug', $slug)->whereKeyNot($area->getKey())->exists(), 422, 'Ya existe un área con un nombre equivalente.');
        $area->update($data + ['slug' => $slug]);

        return back()->with('status', 'Área actualizada.');
    }

    public function storeSource(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:finding_sources,name'],
            'is_invima' => ['required', 'boolean'],
        ]);
        FindingSource::create($data + ['is_active' => true]);

        return back()->with('status', 'Fuente creada correctamente.');
    }

    public function updateSource(Request $request, FindingSource $source): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('finding_sources')->ignore($source)],
            'is_invima' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'],
        ]);
        $source->update($data);

        return back()->with('status', 'Fuente actualizada.');
    }

    private function authorizeAdministrator(Request $request): void
    {
        abort_unless($request->user()->role === 'administrator', 403);
    }
}
