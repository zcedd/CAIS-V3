<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Fund;
use App\Models\User;
use App\Policies\Concerns\ChecksDepartmentPermission;
use App\Support\PermissionName;

class FundPolicy
{
    use ChecksDepartmentPermission;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Department $department): bool
    {
        return $this->allows(
            $user,
            PermissionName::FundViewAny,
            $user->department_id === $department->id,
        );
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Fund $fund): bool
    {
        return $this->allows(
            $user,
            PermissionName::FundView,
            $user->department_id === $fund->department_id,
        );
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Department $department): bool
    {
        return $this->allows(
            $user,
            PermissionName::FundCreate,
            $user->department_id === $department->id,
        );
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Fund $fund): bool
    {
        return $this->allows(
            $user,
            PermissionName::FundUpdate,
            $user->department_id === $fund->department_id,
        );
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Fund $fund): bool
    {
        return $this->allows(
            $user,
            PermissionName::FundDelete,
            $user->department_id === $fund->department_id,
        );
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Fund $fund): bool
    {
        return $this->update($user, $fund);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Fund $fund): bool
    {
        return $this->delete($user, $fund);
    }
}
