<?php

namespace App\Services\Disbursement;

use App\Enums\DisbursementApprovalTaskStatus;
use App\Enums\PermissionName;
use App\Models\DisbursementApprovalPolicy;
use App\Models\DisbursementApprovalStage;
use App\Models\DisbursementApprovalTask;
use App\Models\DisbursementBatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DisbursementAuthorizer
{
    public function isAssigned(User $user, DisbursementApprovalStage $stage): bool
    {
        if ($stage->user_id !== null) {
            return (int) $stage->user_id === (int) $user->id;
        }

        if ($stage->role_name === null || $stage->role_name === '') {
            return false;
        }

        return $user->hasRole($stage->role_name);
    }

    public function assertCanDecide(User $user, DisbursementBatch $batch, DisbursementApprovalTask $task): void
    {
        $task->loadMissing('stage.policy');
        $policy = $batch->policy ?? $task->stage?->policy;

        if (! $policy instanceof DisbursementApprovalPolicy) {
            throw new AuthorizationException('This disbursement batch has no approval policy.');
        }

        if (! $policy->allow_self_approval && (int) $batch->created_by === (int) $user->id) {
            throw new AuthorizationException('You cannot approve a disbursement batch you created.');
        }

        $stage = $task->stage;

        if (! $stage instanceof DisbursementApprovalStage || ! $this->isAssigned($user, $stage)) {
            throw new AuthorizationException('You are not assigned to this approval stage.');
        }

        if ($task->status !== DisbursementApprovalTaskStatus::Pending) {
            throw new AuthorizationException('This approval stage is not actionable.');
        }

        if ($task->revision !== $batch->revision) {
            throw new AuthorizationException('This approval task does not match the submitted revision.');
        }

        if (! $user->can(PermissionName::DisbursementApprove->value)
            && ! $user->can(PermissionName::DisbursementReturn->value)
            && ! $user->can(PermissionName::DisbursementReject->value)) {
            throw new AuthorizationException('You are not allowed to decide this disbursement batch.');
        }
    }

    public function assertCanModifyAmounts(User $user, DisbursementApprovalPolicy $policy): void
    {
        if (! $policy->allow_amount_modification) {
            throw new AuthorizationException('This approval policy does not allow amount or quantity changes.');
        }

        if (! $user->can(PermissionName::DisbursementModifyBeneficiary->value)) {
            throw new AuthorizationException('You are not allowed to modify approved amounts or quantities.');
        }
    }
}
