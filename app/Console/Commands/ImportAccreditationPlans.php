<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\FindingSource;
use App\Models\ImprovementCase;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ImportAccreditationPlans extends Command
{
    protected $signature = 'cumple:import-accreditation {file} {--dry-run}';

    protected $description = 'Importa oportunidades y acciones asistenciales desde el extracto auditado de acreditación';

    public function handle(): int
    {
        $path = $this->argument('file');
        throw_unless(is_file($path), RuntimeException::class, "No existe el archivo {$path}");
        $records = collect(json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR))
            ->filter(fn (array $record) => ! empty($record['matched_users']));

        $this->table(['Concepto', 'Cantidad'], [
            ['Oportunidades', $records->unique(fn ($row) => $row['source_file'].'|'.$row['opportunity_number'])->count()],
            ['Acciones', $records->count()],
            ['Asignaciones', $records->sum(fn ($row) => count($row['matched_users']))],
        ]);
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $source = FindingSource::firstOrCreate(
            ['name' => 'Informe de acreditación – acreditación condicionada'],
            ['is_invima' => false, 'is_active' => true],
        );
        $area = Area::where('slug', 'direccion-medica')->firstOrFail();
        $administrator = User::where('role', 'administrator')->orderBy('id')->firstOrFail();
        $users = User::whereIn('name', $records->pluck('matched_users')->flatten()->unique())->get()->keyBy('name');

        DB::transaction(function () use ($records, $source, $area, $administrator, $users): void {
            $incomingTaskCodes = collect();
            foreach ($records->groupBy(fn ($row) => $row['source_file'].'|'.$row['opportunity_number']) as $group) {
                $first = $group->first();
                $macro = Str::before($first['macroarea'], ' ');
                $caseCode = "ACR-{$macro}-{$first['opportunity_number']}";
                $causalSteps = collect($first['causal_steps'] ?? $group->pluck('causal_step'))
                    ->filter()->unique()->values();
                $rootCause = $causalSteps->first(fn ($step) => Str::contains(Str::upper(Str::ascii($step)), 'CAUSA RAIZ'))
                    ?: ($first['root_cause'] ?? $causalSteps->last());
                $whys = $causalSteps->reject(fn ($step) => Str::contains(Str::upper(Str::ascii($step)), 'CAUSA RAIZ'))
                    ->take(5)->values()->all();
                $case = ImprovementCase::updateOrCreate(['code' => $caseCode], [
                    'title' => Str::limit($first['standard'] ?: $first['opportunity'], 250, ''),
                    'finding_source_id' => $source->id,
                    'reporting_area_id' => $area->id,
                    'reported_area_id' => null,
                    'reported_by' => $administrator->id,
                    'reported_at' => '2025-11-21',
                    'action_type' => 'improvement',
                    'finding_description' => $first['opportunity'],
                    'status' => 'action_plan',
                    'analysis_method' => 'five_whys',
                    'root_cause' => $rootCause,
                    'analysis_data' => [
                        'whys' => $whys,
                        'macroarea' => $first['macroarea'], 'process' => $first['process'],
                        'institutional_owner' => $first['opportunity_owner'],
                        'source_file' => $first['source_file'], 'sheet' => $first['sheet'],
                    ],
                ]);

                foreach ($group as $record) {
                    $dueAt = $this->parseDate($record['due_date']);
                    $closed = Str::upper($record['status']) === 'CERRADA';
                    $inProgress = Str::contains(Str::upper($record['status']), 'IMPLEMENTACI');
                    $taskCode = "{$caseCode}-R{$record['row']}";
                    $incomingTaskCodes->push($taskCode);
                    $assignees = collect($record['matched_users'])->map(fn ($name) => $users->get($name)?->id)->filter()->unique()->values();
                    throw_if($assignees->isEmpty(), RuntimeException::class, "Sin usuario válido para {$taskCode}");
                    $existingTask = Task::where('code', $taskCode)->first();
                    $taskValues = [
                        'title' => Str::limit(Str::squish($record['action']), 250, ''),
                        'description' => $record['action']."\n\nResponsables en la matriz: ".$record['responsible_text']."\nOrigen: {$record['macroarea']}, fila {$record['row']}.",
                        'expected_result' => $record['deliverables'] ?: null,
                        'area_id' => $area->id,
                        'improvement_case_id' => $case->id,
                        'created_by' => $administrator->id,
                        'priority' => 'high',
                        'status' => $closed ? 'completed' : ($inProgress ? 'in_progress' : 'pending'),
                        'progress' => $closed ? 100 : ($inProgress ? 50 : 0),
                        'due_at' => $dueAt,
                        'started_at' => $inProgress ? now() : null,
                        'completed_at' => $closed ? ($dueAt ?? now()) : null,
                    ];
                    if (! $existingTask) {
                        $taskValues += ['assigned_to' => $assignees->first(), 'assignee_type' => 'internal'];
                    }
                    $task = Task::updateOrCreate(['code' => $taskCode], $taskValues);
                    if (! $existingTask || ! $task->assignees()->exists()) {
                        $task->assignees()->sync($assignees);
                    }
                }
            }

            Task::withTrashed()->where('code', 'like', 'ACR-%')->whereNotIn('code', $incomingTaskCodes)
                ->get()->each->forceDelete();
            ImprovementCase::withTrashed()->where('code', 'like', 'ACR-%')->whereDoesntHave('tasks')
                ->get()->each->forceDelete();
        });

        $this->info('Importación de acreditación completada.');

        return self::SUCCESS;
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        $corrections = ['31/12/0206' => '31/12/2026', '31/06/2026' => '30/06/2026', '28/20/2027' => '28/02/2027'];
        $value = $corrections[$value] ?? $value;
        foreach (['Y-m-d\TH:i:s', 'Y-m-d', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable) {
            }
        }
        throw new RuntimeException("Fecha inválida: {$value}");
    }
}
