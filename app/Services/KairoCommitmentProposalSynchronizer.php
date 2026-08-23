<?php

namespace App\Services;

use App\Models\MeetingMinute;
use App\Models\MinuteCommitmentProposal;
use Illuminate\Support\Str;

class KairoCommitmentProposalSynchronizer
{
    public function sync(MeetingMinute $minute, array $commitments): void
    {
        $receivedKeys = [];
        foreach ($commitments as $commitment) {
            $title = Str::squish((string) ($commitment['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $key = hash('sha256', Str::lower(Str::ascii($title)));
            $receivedKeys[] = $key;
            MinuteCommitmentProposal::withoutGlobalScopes()->updateOrCreate(
                ['meeting_minute_id' => $minute->id, 'external_key' => $key],
                [
                    'organization_id' => $minute->organization_id,
                    'title' => $title,
                    'suggested_responsible' => Str::squish((string) ($commitment['responsible'] ?? '')) ?: null,
                    'suggested_due_date' => Str::squish((string) ($commitment['due_date'] ?? '')) ?: null,
                ]
            );
        }

        MinuteCommitmentProposal::withoutGlobalScopes()->where('meeting_minute_id', $minute->id)
            ->where('status', 'pending')->when($receivedKeys, fn ($query) => $query->whereNotIn('external_key', $receivedKeys))
            ->when(! $receivedKeys, fn ($query) => $query)->delete();
    }
}
