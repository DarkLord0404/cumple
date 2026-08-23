<?php

namespace App\Services;

use App\Models\MeetingMinute;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class InstitutionalMinuteDocument
{
    public function generate(MeetingMinute $minute, int $version): string
    {
        $minute->loadMissing('organization');
        $customTemplate = $minute->organization?->minute_template_path;
        $template = $customTemplate && Storage::disk('local')->exists($customTemplate)
            ? Storage::disk('local')->path($customTemplate)
            : resource_path('templates/plantilla_acta_institucional.docx');
        abort_unless(is_file($template), 500, 'No está disponible la plantilla institucional.');
        $minute->loadMissing(['tasks.assignee', 'attendees']);
        $participants = $minute->attendees->map(fn ($user) => ['name' => $user->name, 'role' => $user->role_label])
            ->concat(collect($minute->external_participants)->map(fn ($item) => ['name' => $item['name'] ?? '', 'role' => 'Participante externo']))
            ->values();
        $tasks = $minute->tasks->values();
        $agendaItems = $this->agendaItems($minute->agenda);

        $processor = new TemplateProcessor($template);
        $processor->setMacroChars('{{', '}}');
        $participantRows = $participants->map(fn ($participant) => [
            'participante1' => $participant['name'],
            'cargo1' => $participant['role'],
        ])->all() ?: [['participante1' => '', 'cargo1' => '']];
        $commitmentRows = $tasks->map(fn ($task, $index) => [
            'compromisoNumero' => (string) ($index + 1).'.',
            'compromiso1' => $task->title,
            'responsable1' => $task->responsible_name,
            'fecha1' => $task->due_at?->format('d/m/Y') ?? '',
        ])->all() ?: [['compromisoNumero' => '1.', 'compromiso1' => '', 'responsable1' => '', 'fecha1' => '']];
        $processor->cloneRowAndSetValues('participante1', $participantRows);
        $processor->cloneRowAndSetValues('compromiso1', $commitmentRows);
        $processor->cloneRowAndSetValues('agenda4', collect($agendaItems)->slice(3)->values()->map(fn ($item, $index) => [
            'agendaNumero4' => (string) ($index + 4).'.',
            'agenda4' => $item,
        ])->all() ?: [['agendaNumero4' => '4.', 'agenda4' => '']]);
        $processor->setValues([
            'fecha' => $minute->held_at->format('d/m/Y'),
            'hora' => $minute->held_at->format('H:i'),
            'num' => $minute->number,
            'ano' => $minute->held_at->format('Y'),
            'titulo' => $minute->title,
            'organizador' => $minute->organizer ?? '',
            'lugar' => $minute->location ?? '',
            'objetivo' => $minute->objective ?? '',
            'agendaNumero1' => '1.',
            'agenda1' => $agendaItems[0] ?? '',
            'agendaNumero2' => '2.',
            'agenda2' => $agendaItems[1] ?? '',
            'agendaNumero3' => '3.',
            'agenda3' => $agendaItems[2] ?? '',
            'desarrollo' => $minute->development ?? '',
            'conclusiones' => $minute->decisions ?? '',
            'numMani' => $minute->title,
            'nomPaciente' => '', 'numDocumento' => '',
            'nomMedico' => data_get($participants, '0.name', ''),
            'compromiso' => data_get($tasks, '0.title', ''),
        ]);
        $directory = "minutes/{$minute->id}";
        Storage::disk('local')->makeDirectory($directory);
        $path = "{$directory}/acta-{$minute->number}-v{$version}.docx";
        $processor->saveAs(Storage::disk('local')->path($path));

        return $path;
    }

    /** @return array<int, string> */
    private function agendaItems(?string $agenda): array
    {
        return collect(preg_split('/\R+/u', trim((string) $agenda)) ?: [])
            ->map(fn ($item) => trim((string) preg_replace('/^\s*(?:\d+[.)]|[-–—•*])\s*/u', '', $item)))
            ->filter()
            ->values()
            ->all();
    }
}
