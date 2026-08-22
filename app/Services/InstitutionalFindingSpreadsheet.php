<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class InstitutionalFindingSpreadsheet
{
    public function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($path);
        $sheet = $workbook->getSheetByName('REPORTE');
        if (! $sheet || ! str_contains(mb_strtoupper((string) $sheet->getCell('A4')->getValue()), 'REPORTE DEL HALLAZGO')) {
            throw ValidationException::withMessages(['excel' => 'El archivo no corresponde al formato institucional de reporte y plan de acción.']);
        }

        $date = $sheet->getCell('B5')->getCalculatedValue();
        if (is_numeric($date)) {
            $date = CarbonImmutable::instance(Date::excelToDateTimeObject((float) $date))->toDateString();
        } else {
            try {
                $date = CarbonImmutable::parse((string) $date)->toDateString();
            } catch (\Throwable) {
                $date = now()->toDateString();
            }
        }
        $methodText = mb_strtoupper((string) $sheet->getCell('A17')->getCalculatedValue());

        return [
            'institutional_consecutive' => trim((string) $sheet->getCell('K2')->getCalculatedValue()),
            'reported_at' => $date,
            'reported_person_name' => trim((string) $sheet->getCell('F5')->getCalculatedValue()),
            'reported_person_position' => trim((string) $sheet->getCell('J5')->getCalculatedValue()),
            'reporting_area_name' => trim((string) $sheet->getCell('C6')->getCalculatedValue()),
            'source_name' => trim((string) $sheet->getCell('G6')->getCalculatedValue()),
            'reported_area_name' => trim((string) $sheet->getCell('E7')->getCalculatedValue()),
            'action_type' => str_contains(mb_strtoupper((string) $sheet->getCell('H7')->getCalculatedValue()), 'CORRECTIVA') ? 'corrective' : 'improvement',
            'finding_description' => trim((string) $sheet->getCell('A10')->getCalculatedValue()),
            'urgency_score' => $this->integer($sheet->getCell('A16')->getCalculatedValue()),
            'scope_score' => $this->integer($sheet->getCell('D16')->getCalculatedValue()),
            'evolution_score' => $this->integer($sheet->getCell('F16')->getCalculatedValue()),
            'priority_score' => $this->integer($sheet->getCell('H16')->getCalculatedValue()),
            'analysis_method' => str_contains($methodText, 'CAUSA EFECTO') ? 'cause_effect' : (str_contains($methodText, 'CINCO') ? 'five_whys' : null),
        ];
    }

    private function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
