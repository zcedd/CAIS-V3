<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\DisbursementBatch;
use App\Models\User;
use App\Policies\Concerns\ChecksDepartmentPermission;

class DisbursementBatchPolicy
{
    use ChecksDepartmentPermission;

    public function viewAny(User $user, Department $department): bool
    {
        return $this->allows(
            $user,
            PermissionName::DisbursementViewAny,
            (int) $user->department_id === (int) $department->id,
        );
    }

    public function view(User $user, DisbursementBatch $batch): bool
    {
        if ($user->hasRole(RoleName::Executive) && $user->can(PermissionName::DisbursementView->value)) {
            return true;
        }

        return $this->allows($user, PermissionName::DisbursementView, $this->inDepartment($user, $batch));
    }

    public function create(User $user, Department $department): bool
    {
        return $this->allows(
            $user,
            PermissionName::DisbursementCreate,
            (int) $user->department_id === (int) $department->id,
        );
    }

    public function update(User $user, DisbursementBatch $batch): bool
    {
        return $batch->isEditable()
            && $this->allows($user, PermissionName::DisbursementUpdate, $this->inDepartment($user, $batch));
    }

    public function validateBatch(User $user, DisbursementBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DisbursementValidate, $this->inDepartment($user, $batch));
    }

    public function submit(User $user, DisbursementBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DisbursementSubmit, $this->inDepartment($user, $batch));
    }

    public function approve(User $user, DisbursementBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DisbursementApprove);
    }

    public function returnBatch(User $user, DisbursementBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DisbursementReturn);
    }

    public function reject(User $user, DisbursementBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DisbursementReject);
    }

    public function modifyBeneficiary(User $user, DisbursementBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DisbursementModifyBeneficiary);
    }

    public function finalize(User $user, DisbursementBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DisbursementFinalize);
    }

    public function schedule(User $user, DisbursementBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DisbursementSchedule, $this->inDepartment($user, $batch));
    }

    public function execute(User $user, DisbursementBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DisbursementExecute, $this->inDepartment($user, $batch));
    }

    public function viewEvidence(User $user, DisbursementBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DisbursementViewEvidence, $this->inDepartment($user, $batch));
    }

    public function downloadEvidence(User $user, DisbursementBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DisbursementDownloadEvidence, $this->inDepartment($user, $batch));
    }

    public function issueReceipt(User $user, DisbursementBatch $batch): bool
    {
        return $this->execute($user, $batch)
            && $user->can(PermissionName::DisbursementIssueReceipt->value);
    }

    public function reprintReceipt(User $user, DisbursementBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DisbursementReprintReceipt, $this->inDepartment($user, $batch));
    }

    public function voidTransaction(User $user, DisbursementBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DisbursementVoidTransaction, $this->inDepartment($user, $batch));
    }

    public function assignPersonnel(User $user, DisbursementBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DisbursementAssignPersonnel, $this->inDepartment($user, $batch));
    }

    public function waiveLivePhoto(User $user, DisbursementBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DisbursementWaiveLivePhoto, $this->inDepartment($user, $batch));
    }

    public function reconcile(User $user, DisbursementBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DisbursementReconcile, $this->inDepartment($user, $batch))
            || $this->allows($user, PermissionName::DisbursementView, $this->inDepartment($user, $batch));
    }

    public function complete(User $user, DisbursementBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DisbursementComplete, $this->inDepartment($user, $batch));
    }

    public function cancel(User $user, DisbursementBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DisbursementCancel, $this->inDepartment($user, $batch));
    }

    public function reopen(User $user, DisbursementBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DisbursementUpdate, $this->inDepartment($user, $batch));
    }

    private function inDepartment(User $user, DisbursementBatch $batch): bool
    {
        return (int) $user->department_id === (int) $batch->department_id;
    }

    private function decisionAllowed(User $user, DisbursementBatch $batch, PermissionName $permission): bool
    {
        if (! $user->can($permission->value)) {
            return false;
        }

        if ($this->inDepartment($user, $batch)) {
            return true;
        }

        return $user->hasRole(RoleName::Executive);
    }
}
