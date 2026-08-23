<?php

namespace App\Http\Controllers;

use App\Models\ImprovementCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CaseAnalysisController extends Controller
{
    private const CAUSE_CATEGORIES = [
        'personal' => 'Personal, habilidad o técnica',
        'protocols' => 'Adherencia a protocolos o guías',
        'communication' => 'Comunicación, atención o conocimiento',
        'equipment' => 'Equipos y tecnología',
        'resources' => 'Recursos e insumos',
        'procedures' => 'Procedimientos y documentación',
        'environment' => 'Entorno e infraestructura',
        'measurement' => 'Supervisión, medición e indicadores',
    ];

    public function updatePrioritization(Request $request, ImprovementCase $case): RedirectResponse
    {
        $data = $request->validate([
            'urgency_score' => ['required', 'integer', 'min:0', 'max:10'],
            'scope_score' => ['required', 'integer', 'min:0', 'max:10'],
            'evolution_score' => ['required', 'integer', 'min:0', 'max:10'],
            'validation_notes' => ['nullable', 'string'],
        ]);
        $data['priority_score'] = $data['urgency_score'] + $data['scope_score'] + $data['evolution_score'];
        $data['analysis_method'] = $case->source->is_invima || $data['priority_score'] > 4
            ? 'cause_effect'
            : 'five_whys';
        $data += ['validated_by' => $request->user()->id, 'validated_at' => now(), 'status' => 'analysis'];
        $case->update($data);

        return back()->with('status', 'Priorización guardada. Se habilitó el análisis correspondiente.');
    }

    public function updateAnalysis(Request $request, ImprovementCase $case): RedirectResponse
    {
        abort_if(! $case->analysis_method, 422, 'Primero debe realizar la priorización.');
        $rules = [
            'immediate_correction' => ['nullable', 'string'],
            'root_cause' => ['required', 'string'],
        ];
        if ($case->analysis_method === 'five_whys') {
            $rules['whys'] = ['required', 'array', 'size:5'];
            $rules['whys.*'] = ['required', 'string'];
        } else {
            $rules['cause_categories'] = ['required', 'array', 'min:1'];
            $rules['cause_categories.*'] = ['string', Rule::in(array_keys(self::CAUSE_CATEGORIES))];
            $rules['cause_descriptions'] = ['required', 'array'];
            foreach ($request->input('cause_categories', []) as $category) {
                if (isset(self::CAUSE_CATEGORIES[$category])) {
                    $rules["cause_descriptions.{$category}"] = ['required', 'string'];
                }
            }
        }
        $data = $request->validate($rules);
        $analysisData = $case->analysis_method === 'five_whys'
            ? ['whys' => $data['whys']]
            : ['causes' => collect($data['cause_categories'])->map(fn ($category) => [
                'id' => $category,
                'category' => self::CAUSE_CATEGORIES[$category],
                'description' => $data['cause_descriptions'][$category],
            ])->values()->all()];
        $case->update([
            'immediate_correction' => $data['immediate_correction'] ?? null,
            'root_cause' => $data['root_cause'],
            'analysis_data' => $analysisData,
            'status' => 'action_plan',
        ]);

        return back()->with('status', 'Análisis y causa raíz guardados.');
    }
}
