<?php

namespace App\Services;

use App\Models\MeetingMinute;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class InstitutionalMinuteDocument
{
    public function generate(MeetingMinute $minute): string
    {
        $template = resource_path('templates/plantilla_acta_institucional.docx');
        abort_unless(is_file($template), 500, 'No está disponible la plantilla institucional.');
        $minute->loadMissing(['tasks.assignee', 'attendees']);
        $participants = $minute->attendees->map(fn ($user) => ['name' => $user->name, 'role' => $user->role_label])
            ->concat(collect($minute->external_participants)->map(fn ($item) => ['name' => $item['name'] ?? '', 'role' => 'Participante externo']))
            ->values();
        $tasks = $minute->tasks->values();

        $processor = new TemplateProcessor($template);
        $processor->setMacroChars('{{', '}}');
        $processor->setValues([
            'fecha' => $minute->held_at->format('d/m/Y'),
            'hora' => $minute->held_at->format('H:i'),
            'num' => $minute->number,
            'ano' => $minute->held_at->format('Y'),
            'titulo' => $minute->title,
            'organizador' => $minute->organizer ?? '',
            'lugar' => $minute->location ?? '',
            'objetivo' => $minute->objective ?? '',
            'agenda' => $minute->agenda ?? '',
            'desarrollo' => $minute->development ?? '',
            'conclusiones' => $minute->decisions ?? '',
            'numMani' => $minute->title,
            'nomPaciente' => '', 'numDocumento' => '',
            'nomMedico' => data_get($participants, '0.name', ''),
            'participante1' => data_get($participants, '0.name', ''), 'cargo1' => data_get($participants, '0.role', ''),
            'participante2' => data_get($participants, '1.name', ''), 'cargo2' => data_get($participants, '1.role', ''),
            'participante3' => data_get($participants, '2.name', ''), 'cargo3' => data_get($participants, '2.role', ''),
            'compromiso' => data_get($tasks, '0.title', ''),
            'compromiso1' => data_get($tasks, '0.title', ''), 'responsable1' => $tasks->get(0)?->responsible_name ?? '', 'fecha1' => $tasks->get(0)?->due_at?->format('d/m/Y') ?? '',
            'compromiso2' => data_get($tasks, '1.title', ''), 'responsable2' => $tasks->get(1)?->responsible_name ?? '', 'fecha2' => $tasks->get(1)?->due_at?->format('d/m/Y') ?? '',
        ]);
        $directory = "minutes/{$minute->id}";
        Storage::disk('local')->makeDirectory($directory);
        $path = "{$directory}/acta-{$minute->number}.docx";
        $processor->saveAs(Storage::disk('local')->path($path));

        return $path;
    }
}
