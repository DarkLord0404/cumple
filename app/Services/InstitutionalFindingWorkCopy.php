<?php

namespace App\Services;

use App\Models\ImprovementCase;
use App\Models\OfficialDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InstitutionalFindingWorkCopy
{
    public function generate(ImprovementCase $case, OfficialDocument $original, int $version): array
    {
        $source = Storage::disk($original->disk)->path($original->path);
        $workbook = IOFactory::load($source);
        $report = $workbook->getSheetByName('REPORTE');
        if (! $report) {
            throw ValidationException::withMessages(['document' => 'El Excel original no contiene la hoja REPORTE.']);
        }

        $case->loadMissing(['source', 'reportingArea', 'reportedArea', 'reporter', 'tasks.assignee', 'tasks.assignees']);
        $report->setCellValue('K2', $case->institutional_consecutive ?: $case->code);
        $report->setCellValue('B5', Date::PHPToExcel($case->reported_at));
        $report->getStyle('B5')->getNumberFormat()->setFormatCode('dd/mm/yy');
        $report->setCellValue('F5', $case->reported_person_name ?: $case->reporter?->name);
        $report->setCellValue('J5', $case->reported_person_position ?: $case->reporter?->role_label);
        $report->setCellValue('C6', $case->reportingArea?->name);
        $report->setCellValue('G6', $case->source?->name);
        $report->setCellValue('E7', $case->reportedArea?->name);
        $report->setCellValue('H7', $case->action_type === 'corrective' ? 'Acción correctiva' : 'Acción de mejora');
        $report->setCellValue('A10', $case->finding_description);
        $report->setCellValue('A16', $case->urgency_score);
        $report->setCellValue('D16', $case->scope_score);
        $report->setCellValue('F16', $case->evolution_score);
        $report->setCellValue('H16', $case->priority_score);
        $report->setCellValue('A17', $case->analysis_method === 'cause_effect'
            ? 'SU HALLAZGO REQUIERE UN ANÁLISIS DE CAUSA EFECTO (Pestaña 3)'
            : 'SU HALLAZGO REQUIERE UN ANÁLISIS DE LOS CINCO POR QUÉ (Pestaña 2)');
        $report->setCellValue('A24', $case->validation_notes);

        $case->analysis_method === 'cause_effect'
            ? $this->fillCauseEffect($workbook->getSheetByName('ANALISIS CAUSA EFECTO'), $case)
            : $this->fillFiveWhys($workbook->getSheetByName('ANALISIS CINCO POR QUÉ'), $case);

        $directory = "official-documents/{$case->id}/generated";
        Storage::disk('local')->makeDirectory($directory);
        $safeCode = preg_replace('/[^A-Za-z0-9_-]+/', '-', $case->institutional_consecutive ?: $case->code);
        $name = "copia-trabajo-{$safeCode}-v{$version}.xlsx";
        $path = "{$directory}/{$name}";
        IOFactory::createWriter($workbook, 'Xlsx')->save(Storage::disk('local')->path($path));

        return ['path' => $path, 'name' => $name];
    }

    private function fillFiveWhys(?Worksheet $sheet, ImprovementCase $case): void
    {
        if (! $sheet) {
            throw ValidationException::withMessages(['document' => 'El Excel no contiene la hoja de análisis de cinco porqués.']);
        }
        $sheet->setCellValue('A4', $case->immediate_correction);
        foreach (array_slice(data_get($case->analysis_data, 'whys', []), 0, 5) as $index => $why) {
            $sheet->setCellValue('H'.($index + 9), $why);
        }
        $sheet->setCellValue('A16', $case->root_cause);
        $sheet->setCellValue('E22', $case->reportingArea?->name);
        $sheet->setCellValue('E23', $case->source?->name);
        $sheet->setCellValue('A25', $case->finding_description);
        $this->fillPlan($sheet, $case, 30, ['A', 'F', 'H', 'K'], [['A', 'E'], ['F', 'G'], ['H', 'J'], ['K', 'L']]);
    }

    private function fillCauseEffect(?Worksheet $sheet, ImprovementCase $case): void
    {
        if (! $sheet) {
            throw ValidationException::withMessages(['document' => 'El Excel no contiene la hoja de análisis de causa–efecto.']);
        }
        $sheet->setCellValue('A2', $case->immediate_correction);
        $causes = collect(data_get($case->analysis_data, 'causes', []));
        $description = $causes->isNotEmpty()
            ? $causes->map(fn ($cause) => "• ".data_get($cause, 'category').": ".data_get($cause, 'description'))->join("\n")
            : collect(data_get($case->analysis_data, 'categories', []))->map(fn ($category) => "• {$category}")->join("\n");
        if ($causes->isEmpty() && data_get($case->analysis_data, 'description')) {
            $description .= ($description ? "\n\n" : '').data_get($case->analysis_data, 'description');
        }
        $sheet->setCellValue('A10', $description);
        $sheet->setCellValue('A35', $case->root_cause);
        $this->fillPlan($sheet, $case, 37, ['A', 'I', 'N', 'Q'], [['A', 'H'], ['I', 'M'], ['N', 'P'], ['Q', 'S']]);
    }

    private function fillPlan(Worksheet $sheet, ImprovementCase $case, int $firstRow, array $columns, array $mergedColumns): void
    {
        $tasks = $case->tasks->take(10)->values();
        $templateRows = $sheet->getTitle() === 'ANALISIS CINCO POR QUÉ' ? 3 : 4;
        for ($index = $templateRows; $index < $tasks->count(); $index++) {
            $row = $firstRow + $index;
            $sheet->insertNewRowBefore($row, 1);
            $sourceRow = $row - 1;
            $sheet->duplicateStyle($sheet->getStyle("A{$sourceRow}:S{$sourceRow}"), "A{$row}:S{$row}");
            $sheet->getRowDimension($row)->setRowHeight($sheet->getRowDimension($sourceRow)->getRowHeight());
            foreach ($mergedColumns as [$start, $end]) {
                $sheet->mergeCells("{$start}{$row}:{$end}{$row}");
            }
        }
        foreach ($tasks as $index => $task) {
            $row = $firstRow + $index;
            $resources = collect([
                $task->required_resources ? 'Recursos: '.$task->required_resources : null,
                $task->expected_result ? 'Evidencia esperada: '.$task->expected_result : null,
            ])->filter()->join("\n");
            $sheet->setCellValue("{$columns[0]}{$row}", ($index + 1).'. '.$task->title);
            $sheet->setCellValue("{$columns[1]}{$row}", $task->due_at ? Date::PHPToExcel($task->due_at) : null);
            $sheet->getStyle("{$columns[1]}{$row}")->getNumberFormat()->setFormatCode('dd/mm/yy');
            $sheet->setCellValue("{$columns[2]}{$row}", $task->responsible_name);
            $sheet->setCellValue("{$columns[3]}{$row}", $resources);
            $sheet->getStyle("A{$row}:S{$row}")->getFont()->setBold(false);
            $sheet->getStyle("A{$row}:S{$row}")->getAlignment()->setVertical('center')->setWrapText(true);
            $sheet->getStyle("{$columns[0]}{$row}")->getAlignment()->setHorizontal('left');
            $sheet->getStyle("{$columns[1]}{$row}:{$columns[2]}{$row}")->getAlignment()->setHorizontal('center');
            $sheet->getStyle("{$columns[3]}{$row}")->getAlignment()->setHorizontal('left');
        }
    }
}
