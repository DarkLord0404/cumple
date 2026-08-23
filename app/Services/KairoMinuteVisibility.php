<?php

namespace App\Services;

use App\Models\MeetingMinute;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class KairoMinuteVisibility
{
    public function apply(Builder $query, User $user): Builder
    {
        if ($user->role === 'administrator') {
            return $query;
        }

        $visibility = $user->organization?->kairo_minute_visibility ?? 'administrators';
        if ($visibility === 'everyone') {
            return $query;
        }

        $selected = $visibility === 'selected' && DB::table('organization_kairo_minute_viewers')
            ->where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->exists();

        if ($selected) {
            return $query;
        }

        return $query->where(fn (Builder $minutes) => $minutes->where('source_system', '!=', 'kairo')->orWhereNull('source_system'));
    }

    public function canView(MeetingMinute $minute, User $user): bool
    {
        if ($minute->source_system !== 'kairo' || $user->role === 'administrator') {
            return true;
        }
        $visibility = $user->organization?->kairo_minute_visibility ?? 'administrators';

        return $visibility === 'everyone' || ($visibility === 'selected' && DB::table('organization_kairo_minute_viewers')
            ->where('organization_id', $user->organization_id)->where('user_id', $user->id)->exists());
    }
}
