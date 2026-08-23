<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalWorkflow
{
    public function typeFor(User $user, ?Task $task = null): ?string
    {
        $organization = $user->organization;
        $quality = $this->requires($organization, 'quality') && $this->isApprover($user, 'quality');
        $medical = $this->requires($organization, 'medical') && $this->isApprover($user, 'medical');
        if ($task && $quality && $medical && $task->quality_approved_at && ! $task->medical_approved_at) {
            return 'medical';
        }
        if ($quality) {
            return 'quality';
        }

        return $medical ? 'medical' : null;
    }

    public function isApprover(User $user, string $type): bool
    {
        $configured = DB::table('organization_approvers')->where('organization_id', $user->organization_id)->where('approval_type', $type);
        if ((clone $configured)->exists()) {
            return (clone $configured)->where('user_id', $user->id)->exists();
        }
        if ($type === 'quality') {
            return $user->role === 'quality';
        }

        return $user->role === 'coordinator_medical' && ($user->area()->where('slug', 'direccion-medica')->exists()
            || Area::where('slug', 'direccion-medica')->where('coordinator_id', $user->id)->exists());
    }

    public function requires(?Organization $organization, string $type): bool
    {
        return match ($organization?->approval_policy ?: 'both') {
            'quality' => $type === 'quality',
            'medical' => $type === 'medical',
            default => true,
        };
    }

    public function canClose(Task $task): bool
    {
        $organization = $task->organization_id ? Organization::find($task->organization_id) : null;

        return (! $this->requires($organization, 'quality') || $task->quality_approved_at)
            && (! $this->requires($organization, 'medical') || $task->medical_approved_at);
    }

    public function approvers(Organization $organization, string $type)
    {
        $configuredIds = DB::table('organization_approvers')->where('organization_id', $organization->id)->where('approval_type', $type)->pluck('user_id');
        if ($configuredIds->isNotEmpty()) {
            return User::withoutGlobalScopes()->whereIn('id', $configuredIds)->where('is_active', true)->get();
        }
        if ($type === 'quality') {
            return User::withoutGlobalScopes()->where('organization_id', $organization->id)->where('role', 'quality')->where('is_active', true)->get();
        }

        return User::withoutGlobalScopes()->where('organization_id', $organization->id)->where('role', 'coordinator_medical')->where('is_active', true)
            ->where(function ($query): void {
                $query->whereHas('area', fn ($area) => $area->withoutGlobalScopes()->where('slug', 'direccion-medica'))
                    ->orWhereHas('coordinatedAreas', fn ($area) => $area->withoutGlobalScopes()->where('slug', 'direccion-medica'));
            })->get();
    }
}
