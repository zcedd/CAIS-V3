<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\User;
use App\Policies\Concerns\ChecksDepartmentPermission;

class BeneficiaryPolicy
{
    use ChecksDepartmentPermission;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Department $department): bool
    {
        return $this->allows(
            $user,
            PermissionName::BeneficiaryViewAny,
            $user->department_id === $department->id,
        );
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Beneficiary $beneficiary): bool
    {
        return $this->allows(
            $user,
            PermissionName::BeneficiaryView,
            $this->belongsToRouteDepartment($user),
        );
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Department $department): bool
    {
        return $this->allows(
            $user,
            PermissionName::BeneficiaryCreate,
            $user->department_id === $department->id,
        );
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Beneficiary $beneficiary): bool
    {
        return $this->allows(
            $user,
            PermissionName::BeneficiaryUpdate,
            $this->belongsToRouteDepartment($user),
        );
    }

    private function belongsToRouteDepartment(User $user): bool
    {
        $department = request()->route('department');

        return $department instanceof Department
            && $user->department_id === $department->id;
    }
}
