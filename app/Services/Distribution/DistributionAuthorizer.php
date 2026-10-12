<?php

namespace App\Services\Distribution;

use App\Enums\DistributionApprovalTaskStatus;
use App\Enums\PermissionName;
use App\Models\DistributionApprovalPolicy;
use App\Models\DistributionApprovalStage;
use App\Models\DistributionApprovalTask;
use App\Models\DistributionBatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DistributionAuthorizer
{
    public function isAssigned(User $user, DistributionApprovalStage $stage): bool
    {
        if ($stage->user_id !== null) {
            return (int) $stage->user_id === (int) $user->id;
        }

        if ($stage->role_name === null || $stage->role_name === '') {
            return false;
        }

        return $user->hasRole($stage->role_name);
    }

    public function assertCanDecide(User $user, DistributionBatch $batch, DistributionApprovalTask $task): void
    {
        $task->loadMissing('stage.policy');
        $policy = $batch->policy ?? $task->stage?->policy;

        if (! $policy instanceof DistributionApprovalPolicy) {
            throw new AuthorizationException('This distribution batch has no approval policy.');
        }

        if (! $policy->allow_self_approval && (int) $batch->created_by === (int) $user->id) {
            throw new AuthorizationException('You cannot approve a distribution batch you created.');
        }

        $stage = $task->stage;

        if (! $stage instanceof DistributionApprovalStage || ! $this->isAssigned($user, $stage)) {
            throw new AuthorizationException('You are not assigned to this approval stage.');
        }

        if ($task->status !== DistributionApprovalTaskStatus::Pending) {
            throw new AuthorizationException('This approval stage is not actionable.');
        }

        if ($task->revision !== $batch->revision) {
            throw new AuthorizationException('This approval task does not match the submitted revision.');
        }

        if (! $user->can(PermissionName::DistributionApprove->value)
            && ! $user->can(PermissionName::DistributionReturn->value)
            && ! $user->can(PermissionName::DistributionReject->value)) {
            throw new AuthorizationException('You are not allowed to decide this distribution batch.');
        }
    }

    public function assertCanModifyAmounts(User $user, DistributionApprovalPolicy $policy): void
    {
        if (! $policy->allow_amount_modification) {
            throw new AuthorizationException('This approval policy does not allow amount or quantity changes.');
        }

        if (! $user->can(PermissionName::DistributionModifyBeneficiary->value)) {
            throw new AuthorizationException('You are not allowed to modify approved amounts or quantities.');
        }
    }
}
