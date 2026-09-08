<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Models\Workflow;

class WorkflowPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Department $department): bool
    {
        return $this->belongsToDepartment($user, $department);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Workflow $workflow): bool
    {
        return $user->department_id === $workflow->department_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Department $department): bool
    {
        return $this->belongsToDepartment($user, $department)
            && $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Workflow $workflow): bool
    {
        return $this->view($user, $workflow);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Workflow $workflow): bool
    {
        $workflow->loadMissing('department');

        $department = $workflow->department;

        return $department instanceof Department
            && $this->create($user, $department);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Workflow $workflow): bool
    {
        return $this->update($user, $workflow);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Workflow $workflow): bool
    {
        return $this->delete($user, $workflow);
    }

    private function belongsToDepartment(User $user, Department $department): bool
    {
        return $user->department_id === $department->id;
    }
}
