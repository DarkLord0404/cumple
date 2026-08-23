<?php

namespace App\Services;

use Illuminate\Support\Str;

class KairoMinutesParser
{
    public function parse(?string $markdown): array
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", trim((string) $markdown));
        $sections = $this->sections($markdown);

        return [
            'objective' => $this->metadata($markdown, 'Objetivo de la reunion') ?: $this->metadata($markdown, 'Objetivo de la reunión'),
            'agenda' => $this->plain($sections['temas tratados'] ?? ''),
            'development' => $this->plain($sections['texto del acta (lo discutido)'] ?? $sections['desarrollo'] ?? ''),
            'decisions' => $this->plain($sections['conclusion'] ?? $sections['conclusión'] ?? $sections['conclusiones'] ?? ''),
            'participants' => $this->participantNames($this->metadata($markdown, 'Asistentes')),
            'commitments' => $this->commitments($sections['tareas y acciones pendientes'] ?? ''),
        ];
    }

    private function sections(string $markdown): array
    {
        $sections = [];
        if (! preg_match_all('/^##\s+(.+?)\s*$\n(.*?)(?=^##\s+|\z)/msu', $markdown, $matches, PREG_SET_ORDER)) {
            return [];
        }
        foreach ($matches as $match) {
            $sections[Str::lower(Str::ascii(trim($match[1])))] = trim($match[2]);
        }

        return $sections;
    }

    private function metadata(string $markdown, string $label): ?string
    {
        $quoted = preg_quote($label, '/');
        if (preg_match('/^-\s+\*\*'.$quoted.':\*\*\s*(.+)$/miu', $markdown, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    private function participantNames(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return collect(preg_split('/\s*;\s*|\s*,\s*(?=[A-ZÁÉÍÓÚÑ])/u', $value))
            ->map(fn ($name) => trim((string) $name, " ."))->filter()->unique()->values()->all();
    }

    private function commitments(string $section): array
    {
        $rows = [];
        foreach (preg_split('/\n/', $section) as $line) {
            if (! str_starts_with(trim($line), '|')) {
                continue;
            }
            $cells = array_map('trim', array_slice(explode('|', trim($line)), 1, -1));
            if (count($cells) < 3 || Str::lower($cells[0]) === 'tarea' || preg_match('/^-+$/', $cells[0])) {
                continue;
            }
            $rows[] = ['title' => $this->plain($cells[0]), 'responsible' => $this->plain($cells[1]), 'due_date' => $this->plain($cells[2])];
        }

        return $rows;
    }

    private function plain(string $markdown): string
    {
        $text = preg_replace('/^#{1,6}\s+/m', '', trim($markdown));
        $text = preg_replace('/\*\*(.*?)\*\*/su', '$1', $text);
        $text = preg_replace('/__(.*?)__/su', '$1', $text);
        $text = preg_replace('/\[(.*?)\]\([^)]*\)/u', '$1', $text);
        $text = preg_replace('/\s{2,}$/m', '', $text);

        return trim($text);
    }
}
