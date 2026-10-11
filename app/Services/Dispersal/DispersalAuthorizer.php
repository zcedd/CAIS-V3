<?php

namespace App\Services\Dispersal;

use App\Enums\DispersalApprovalTaskStatus;
use App\Enums\PermissionName;
use App\Models\DispersalApprovalPolicy;
use App\Models\DispersalApprovalStage;
use App\Models\DispersalApprovalTask;
use App\Models\DispersalBatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DispersalAuthorizer
{
    public function isAssigned(User $user, DispersalApprovalStage $stage): bool
    {
        if ($stage->user_id !== null) {
            return (int) $stage->user_id === (int) $user->id;
        }

        if ($stage->role_name === null || $stage->role_name === '') {
            return false;
        }

        return $user->hasRole($stage->role_name);
    }

    public function assertCanDecide(User $user, DispersalBatch $batch, DispersalApprovalTask $task): void
    {
        $task->loadMissing('stage.policy');
        $policy = $batch->policy ?? $task->stage?->policy;

        if (! $policy instanceof DispersalApprovalPolicy) {
            throw new AuthorizationException('This dispersal batch has no approval policy.');
        }

        if (! $policy->allow_self_approval && (int) $batch->created_by === (int) $user->id) {
            throw new AuthorizationException('You cannot approve a dispersal batch you created.');
        }

        $stage = $task->stage;

        if (! $stage instanceof DispersalApprovalStage || ! $this->isAssigned($user, $stage)) {
            throw new AuthorizationException('You are not assigned to this approval stage.');
        }

        if ($task->status !== DispersalApprovalTaskStatus::Pending) {
            throw new AuthorizationException('This approval stage is not actionable.');
        }

        if ($task->revision !== $batch->revision) {
            throw new AuthorizationException('This approval task does not match the submitted revision.');
        }

        if (! $user->can(PermissionName::DispersalApprove->value)
            && ! $user->can(PermissionName::DispersalReturn->value)
            && ! $user->can(PermissionName::DispersalReject->value)) {
            throw new AuthorizationException('You are not allowed to decide this dispersal batch.');
        }
    }

    public function assertCanModifyAmounts(User $user, DispersalApprovalPolicy $policy): void
    {
        if (! $policy->allow_amount_modification) {
            throw new AuthorizationException('This approval policy does not allow amount or quantity changes.');
        }

        if (! $user->can(PermissionName::DispersalModifyBeneficiary->value)) {
            throw new AuthorizationException('You are not allowed to modify approved amounts or quantities.');
        }
    }
}
