<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Policies\Concerns\ChecksDepartmentPermission;

class ProgramPolicy
{
    use ChecksDepartmentPermission;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Department $department): bool
    {
        return $this->allows(
            $user,
            PermissionName::ProgramViewAny,
            $user->department_id === $department->id,
        );
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Program $program): bool
    {
        return $this->allows(
            $user,
            PermissionName::ProgramView,
            $user->department_id === $program->department_id,
        );
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Department $department): bool
    {
        return $this->allows(
            $user,
            PermissionName::ProgramCreate,
            $user->department_id === $department->id,
        );
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Program $program): bool
    {
        return $this->allows(
            $user,
            PermissionName::ProgramUpdate,
            $user->department_id === $program->department_id,
        );
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Program $program): bool
    {
        return $this->allows(
            $user,
            PermissionName::ProgramDelete,
            $user->department_id === $program->department_id,
        );
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Program $program): bool
    {
        return $this->update($user, $program);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Program $program): bool
    {
        return $this->delete($user, $program);
    }

    /**
     * Determine whether the user can download assistance for the program.
     */
    public function downloadAssistance(User $user, Program $program): bool
    {
        return $this->allows(
            $user,
            PermissionName::ProgramDownloadAssistance,
            $user->department_id === $program->department_id,
        );
    }
}
