<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Department;
use App\Models\User;
use App\Models\Workflow;
use App\Policies\Concerns\ChecksDepartmentPermission;

class WorkflowPolicy
{
    use ChecksDepartmentPermission;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Department $department): bool
    {
        return $this->allows(
            $user,
            PermissionName::WorkflowViewAny,
            $this->belongsToDepartment($user, $department),
        );
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Workflow $workflow): bool
    {
        return $this->allows(
            $user,
            PermissionName::WorkflowView,
            $user->department_id === $workflow->department_id,
        );
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Department $department): bool
    {
        return $this->allows(
            $user,
            PermissionName::WorkflowCreate,
            $this->belongsToDepartment($user, $department),
        );
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Workflow $workflow): bool
    {
        return $this->allows(
            $user,
            PermissionName::WorkflowUpdate,
            $user->department_id === $workflow->department_id,
        );
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Workflow $workflow): bool
    {
        return $this->allows(
            $user,
            PermissionName::WorkflowDelete,
            $user->department_id === $workflow->department_id,
        );
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

    public function publish(User $user, Workflow $workflow): bool
    {
        return $this->allows(
            $user,
            PermissionName::WorkflowPublish,
            $user->department_id === $workflow->department_id,
        );
    }

    public function version(User $user, Workflow $workflow): bool
    {
        return $this->allows(
            $user,
            PermissionName::WorkflowVersion,
            $user->department_id === $workflow->department_id,
        );
    }

    public function assign(User $user, Workflow $workflow): bool
    {
        return $this->allows(
            $user,
            PermissionName::WorkflowAssign,
            $user->department_id === $workflow->department_id,
        );
    }

    public function manage(User $user, Workflow $workflow): bool
    {
        return $this->allows(
            $user,
            PermissionName::WorkflowManage,
            $user->department_id === $workflow->department_id,
        );
    }

    public function viewTask(User $user, Workflow $workflow): bool
    {
        return $this->allows(
            $user,
            PermissionName::WorkflowTaskView,
            $user->department_id === $workflow->department_id,
        );
    }

    public function reassignTask(User $user, Workflow $workflow): bool
    {
        return $this->allows(
            $user,
            PermissionName::WorkflowTaskReassign,
            $user->department_id === $workflow->department_id,
        );
    }

    public function overrideTask(User $user, Workflow $workflow): bool
    {
        return $this->allows(
            $user,
            PermissionName::WorkflowTaskOverride,
            $user->department_id === $workflow->department_id,
        );
    }

    private function belongsToDepartment(User $user, Department $department): bool
    {
        return $user->department_id === $department->id;
    }
}
