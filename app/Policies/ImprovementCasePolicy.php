<?php

namespace App\Policies;

use App\Models\ImprovementCase;
use App\Models\User;
use App\Services\ApprovalWorkflow;

class ImprovementCasePolicy
{
    public function __construct(private readonly ApprovalWorkflow $approvalWorkflow) {}

    public function view(User $user, ImprovementCase $case): bool
    {
        return ImprovementCase::query()
            ->visibleTo($user, $this->approvalWorkflow->typeFor($user) !== null)
            ->whereKey($case->id)
            ->exists();
    }
}
