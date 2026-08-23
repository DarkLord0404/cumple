<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\MeetingMinute;
use App\Models\User;
use App\Services\KairoMinutesParser;
use App\Services\KairoCommitmentProposalSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KairoMeetingImportController extends Controller
{
    public function __invoke(Request $request, KairoMinutesParser $parser, KairoCommitmentProposalSynchronizer $proposals): JsonResponse
    {
        $token = $request->bearerToken();
        abort_unless(is_string($token) && strlen($token) >= 40, 401, 'Credencial de integración ausente.');

        $connection = IntegrationConnection::query()
            ->where('provider', 'kairo')
            ->where('token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->first();
        abort_unless($connection, 401, 'Credencial de integración inválida.');

        $data = $request->validate([
            'external_reference' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'held_at' => ['required', 'date'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'minutes_markdown' => ['nullable', 'string'],
            'transcript' => ['nullable', 'string'],
            'participants' => ['nullable', 'array'],
            'participants.*.name' => ['required', 'string', 'max:255'],
            'participants.*.email' => ['nullable', 'email', 'max:255'],
        ]);

        $parsed = $parser->parse($data['minutes_markdown'] ?? null);
        $participants = collect($data['participants'] ?? [])->concat(
            collect($parsed['participants'])->map(fn ($name) => ['name' => $name])
        )->unique(fn ($participant) => Str::lower(Str::ascii($participant['name'])))->values()->all();
        [$internalIds, $externalParticipants] = $this->matchParticipants(
            $connection->organization_id,
            $participants
        );

        $minute = DB::transaction(function () use ($connection, $data, $parsed, $internalIds, $externalParticipants, $proposals): MeetingMinute {
            $minute = MeetingMinute::withoutGlobalScopes()->firstOrNew(
                [
                    'organization_id' => $connection->organization_id,
                    'source_system' => 'kairo',
                    'external_reference' => $data['external_reference'],
                ]
            );
            if (! $minute->exists) {
                $minute->number = 'KAIRO-'.now()->format('Y').'-'.Str::upper(Str::random(6));
            }
            $minute->fill([
                    'title' => $data['title'],
                    'meeting_type' => 'kairo',
                    'organizer' => $data['organizer'] ?? null,
                    'created_by' => $connection->created_by,
                    'held_at' => $data['held_at'],
                    'location' => $data['location'] ?? 'Google Meet',
                    'external_participants' => $externalParticipants,
                    'objective' => $parsed['objective'],
                    'agenda' => $parsed['agenda'],
                    'development' => $parsed['development'],
                    'decisions' => $parsed['decisions'],
                    'status' => 'draft',
                    'external_payload' => $data + ['parsed_commitments' => $parsed['commitments']],
                ])->save();
            $minute->attendees()->sync($internalIds);
            $proposals->sync($minute, $parsed['commitments']);
            $connection->forceFill(['last_used_at' => now()])->save();

            return $minute;
        });

        return response()->json([
            'message' => $minute->wasRecentlyCreated ? 'Borrador importado.' : 'Borrador actualizado.',
            'minute_id' => $minute->id,
            'status' => 'draft',
            'matched_participants' => count($internalIds),
            'external_participants' => count($externalParticipants),
            'proposed_commitments' => count($parsed['commitments']),
        ], $minute->wasRecentlyCreated ? 201 : 200);
    }

    private function matchParticipants(int $organizationId, array $participants): array
    {
        $users = User::withoutGlobalScopes()->where('organization_id', $organizationId)->where('is_active', true)->get();
        $internal = [];
        $external = [];

        foreach ($participants as $participant) {
            $email = Str::lower(trim((string) ($participant['email'] ?? '')));
            $name = trim($participant['name']);
            $normalizedName = Str::lower(Str::ascii($name));
            $user = $users->first(function (User $candidate) use ($email, $normalizedName): bool {
                if ($email !== '' && Str::lower($candidate->email) === $email) {
                    return true;
                }
                return Str::lower(Str::ascii(trim($candidate->name))) === $normalizedName;
            });

            if ($user) {
                $internal[] = $user->id;
            } else {
                $external[] = array_filter(['name' => $name, 'email' => $email ?: null]);
            }
        }

        return [array_values(array_unique($internal)), $external];
    }
}
