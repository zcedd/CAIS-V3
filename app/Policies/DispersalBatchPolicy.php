<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\DispersalBatch;
use App\Models\User;
use App\Policies\Concerns\ChecksDepartmentPermission;

class DispersalBatchPolicy
{
    use ChecksDepartmentPermission;

    public function viewAny(User $user, Department $department): bool
    {
        return $this->allows(
            $user,
            PermissionName::DispersalViewAny,
            (int) $user->department_id === (int) $department->id,
        );
    }

    public function view(User $user, DispersalBatch $batch): bool
    {
        if ($user->hasRole(RoleName::Governor) && $user->can(PermissionName::DispersalView->value)) {
            return true;
        }

        return $this->allows($user, PermissionName::DispersalView, $this->inDepartment($user, $batch));
    }

    public function create(User $user, Department $department): bool
    {
        return $this->allows(
            $user,
            PermissionName::DispersalCreate,
            (int) $user->department_id === (int) $department->id,
        );
    }

    public function update(User $user, DispersalBatch $batch): bool
    {
        return $batch->isEditable()
            && $this->allows($user, PermissionName::DispersalUpdate, $this->inDepartment($user, $batch));
    }

    public function validateBatch(User $user, DispersalBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DispersalValidate, $this->inDepartment($user, $batch));
    }

    public function submit(User $user, DispersalBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DispersalSubmit, $this->inDepartment($user, $batch));
    }

    public function approve(User $user, DispersalBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DispersalApprove);
    }

    public function returnBatch(User $user, DispersalBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DispersalReturn);
    }

    public function reject(User $user, DispersalBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DispersalReject);
    }

    public function modifyBeneficiary(User $user, DispersalBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DispersalModifyBeneficiary);
    }

    public function finalize(User $user, DispersalBatch $batch): bool
    {
        return $this->decisionAllowed($user, $batch, PermissionName::DispersalFinalize);
    }

    public function schedule(User $user, DispersalBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DispersalSchedule, $this->inDepartment($user, $batch));
    }

    public function execute(User $user, DispersalBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DispersalExecute, $this->inDepartment($user, $batch));
    }

    public function reconcile(User $user, DispersalBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DispersalReconcile, $this->inDepartment($user, $batch))
            || $this->allows($user, PermissionName::DispersalView, $this->inDepartment($user, $batch));
    }

    public function complete(User $user, DispersalBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DispersalComplete, $this->inDepartment($user, $batch));
    }

    public function cancel(User $user, DispersalBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DispersalCancel, $this->inDepartment($user, $batch));
    }

    public function reopen(User $user, DispersalBatch $batch): bool
    {
        return $this->allows($user, PermissionName::DispersalUpdate, $this->inDepartment($user, $batch));
    }

    private function inDepartment(User $user, DispersalBatch $batch): bool
    {
        return (int) $user->department_id === (int) $batch->department_id;
    }

    private function decisionAllowed(User $user, DispersalBatch $batch, PermissionName $permission): bool
    {
        if (! $user->can($permission->value)) {
            return false;
        }

        if ($this->inDepartment($user, $batch)) {
            return true;
        }

        return $user->hasRole(RoleName::Governor);
    }
}
