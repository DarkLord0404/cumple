<?php

namespace App\Console\Commands;

use App\Models\MeetingMinute;
use App\Services\KairoMinutesParser;
use Illuminate\Console\Command;

class ReparseKairoMinutes extends Command
{
    protected $signature = 'minutes:reparse-kairo';
    protected $description = 'Separa el Markdown almacenado de Kairo en los campos del acta';

    public function handle(KairoMinutesParser $parser): int
    {
        $updated = 0;
        MeetingMinute::withoutGlobalScopes()->where('source_system', 'kairo')->each(function (MeetingMinute $minute) use ($parser, &$updated): void {
            $payload = $minute->external_payload ?? [];
            $markdown = $payload['minutes_markdown'] ?? null;
            if (! $markdown) {
                return;
            }
            $parsed = $parser->parse($markdown);
            $minute->forceFill([
                'objective' => $parsed['objective'], 'agenda' => $parsed['agenda'],
                'development' => $parsed['development'], 'decisions' => $parsed['decisions'],
                'external_payload' => $payload + ['parsed_commitments' => $parsed['commitments']],
            ])->save();
            $updated++;
        });
        $this->info("Actas reestructuradas: {$updated}");

        return self::SUCCESS;
    }
}
