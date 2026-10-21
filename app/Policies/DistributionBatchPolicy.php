<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\DistributionBatch;
use App\Models\User;
use App\Policies\Concerns\ChecksDepartmentPermission;

class DistributionBatchPolicy
{
    use ChecksDepartmentPermission;

    public function viewAny(User $user, Department $department): bool
    {
        return $this->allows(
            $user,
            PermissionName::DistributionViewAny,
            (int) $user->department_id === (int) $department->id,
        );
    }

    public function view(User $user, DistributionBatch $batch): bool
    {
        if ($user->hasAnyRole(RoleName::provinceWideValues()) && $user->can(PermissionName::DistributionView->value)) {
            return true;
        }

        return $this->allows($user, PermissionName::DistributionView, $this->inDepartment($user, $batch));
    }

    public function create(User $user, Department $department): bool
    {
        return $this->allows(
            $user,
            PermissionName::DistributionCreate,
            (int) $user->department_id === (int) $department->id,
        );
    }

    public function update(User $user, DistributionBatch $batch): bool
    {
        return $batch->isEditable()
            && $this->allows($user, PermissionName::DistributionUpdate, $this->inDepartment($user, $batch));
    }

    public function validateBatch(User $user, DistributionBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DistributionValidate, $this->inDepartment($user, $batch));
    }

    public function submit(User $user, DistributionBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DistributionSubmit, $this->inDepartment($user, $batch));
    }

    public function approve(User $user, DistributionBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DistributionApprove);
    }

    public function returnBatch(User $user, DistributionBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DistributionReturn);
    }

    public function reject(User $user, DistributionBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DistributionReject);
    }

    public function modifyBeneficiary(User $user, DistributionBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DistributionModifyBeneficiary);
    }

    public function finalize(User $user, DistributionBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DistributionFinalize);
    }

    public function schedule(User $user, DistributionBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DistributionSchedule, $this->inDepartment($user, $batch));
    }

    public function execute(User $user, DistributionBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DistributionExecute, $this->inDepartment($user, $batch));
    }

    public function viewEvidence(User $user, DistributionBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DistributionViewEvidence, $this->inDepartment($user, $batch));
    }

    public function downloadEvidence(User $user, DistributionBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DistributionDownloadEvidence, $this->inDepartment($user, $batch));
    }

    public function issueReceipt(User $user, DistributionBatch $batch): bool
    {
        return $this->execute($user, $batch)
            && $user->can(PermissionName::DistributionIssueReceipt->value);
    }

    public function reprintReceipt(User $user, DistributionBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DistributionReprintReceipt, $this->inDepartment($user, $batch));
    }

    public function voidTransaction(User $user, DistributionBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DistributionVoidTransaction, $this->inDepartment($user, $batch));
    }

    public function assignPersonnel(User $user, DistributionBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DistributionAssignPersonnel, $this->inDepartment($user, $batch));
    }

    public function waiveLivePhoto(User $user, DistributionBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DistributionWaiveLivePhoto, $this->inDepartment($user, $batch));
    }

    public function reconcile(User $user, DistributionBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DistributionReconcile, $this->inDepartment($user, $batch))
            || $this->allows($user, PermissionName::DistributionView, $this->inDepartment($user, $batch));
    }

    public function complete(User $user, DistributionBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DistributionComplete, $this->inDepartment($user, $batch));
    }

    public function cancel(User $user, DistributionBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DistributionCancel, $this->inDepartment($user, $batch));
    }

    public function reopen(User $user, DistributionBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DistributionUpdate, $this->inDepartment($user, $batch));
    }

    private function inDepartment(User $user, DistributionBatch $batch): bool
    {
        return (int) $user->department_id === (int) $batch->department_id;
    }

    private function decisionAllowed(User $user, DistributionBatch $batch, PermissionName $permission): bool
    {
        if (! $user->can($permission->value)) {
            return false;
        }

        if ($this->inDepartment($user, $batch)) {
            return true;
        }

        return $user->hasAnyRole(RoleName::provinceWideValues());
    }
}
