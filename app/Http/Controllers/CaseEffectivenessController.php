<?php

namespace App\Http\Controllers;

use App\Models\ImprovementCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CaseEffectivenessController extends Controller
{
    public function update(Request $request, ImprovementCase $case): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['administrator', 'quality', 'coordinator']), 403);
        $data = $request->validate([
            'impact_before' => ['required', 'string'],
            'impact_after' => ['required', 'string'],
            'effectiveness_result' => ['required', 'string'],
            'is_effective' => ['required', 'boolean'],
            'closure_notes' => ['nullable', 'string'],
        ]);

        if ($request->boolean('is_effective')) {
            abort_if($case->tasks()->whereNotIn('status', ['completed', 'cancelled'])->exists(), 422, 'No se puede cerrar mientras existan acciones pendientes.');
            $data += ['status' => 'closed', 'closed_at' => now()];
        } else {
            $data += ['status' => 'action_plan', 'closed_at' => null];
        }
        $data += ['effectiveness_evaluated_by' => $request->user()->id, 'effectiveness_evaluated_at' => now()];
        $case->update($data);

        return back()->with('status', $case->status === 'closed' ? 'Plan cerrado como eficaz.' : 'Plan no eficaz: quedó reabierto para nuevas acciones.');
    }
}
