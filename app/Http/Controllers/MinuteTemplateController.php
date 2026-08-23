<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MinuteTemplateController extends Controller
{
    public function edit(Request $request): View
    {
        $this->authorizeAdministrator($request);

        return view('administration.minute-template', ['organization' => $request->user()->organization]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'template' => ['required', 'file', 'mimes:docx', 'max:10240'],
        ]);
        $organization = $request->user()->organization;
        $file = $data['template'];
        $path = $file->storeAs("organizations/{$organization->id}/templates", 'plantilla-acta.docx');
        $organization->update(['minute_template_path' => $path, 'minute_template_name' => $file->getClientOriginalName()]);

        return back()->with('status', 'Plantilla de acta actualizada. Las versiones anteriores se conservan.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $organization = $request->user()->organization;
        if ($organization->minute_template_path) {
            Storage::disk('local')->delete($organization->minute_template_path);
        }
        $organization->update(['minute_template_path' => null, 'minute_template_name' => null]);

        return back()->with('status', 'Se restauró la plantilla predeterminada de CUMPLE.');
    }

    private function authorizeAdministrator(Request $request): void
    {
        abort_unless($request->user()->role === 'administrator', 403);
    }
}
