<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Policies\Concerns\ChecksDepartmentPermission;

class AssistancePolicy
{
    use ChecksDepartmentPermission;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Department $department): bool
    {
        if ($user->can(PermissionName::ProgramApprove->value)) {
            return true;
        }

        return $this->allows(
            $user,
            PermissionName::AssistanceViewAny,
            $user->department_id === $department->id,
        );
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Assistance $assistance): bool
    {
        if ($user->can(PermissionName::ProgramApprove->value)) {
            return true;
        }

        return $this->allows(
            $user,
            PermissionName::AssistanceView,
            $this->belongsToUserDepartment($user, $assistance),
        );
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Program $program): bool
    {
        return $this->allows(
            $user,
            PermissionName::AssistanceCreate,
            $user->department_id === $program->department_id,
        ) && $program->isEncodable();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Assistance $assistance): bool
    {
        return $this->allows(
            $user,
            PermissionName::AssistanceUpdate,
            $this->belongsToUserDepartment($user, $assistance),
        );
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Assistance $assistance): bool
    {
        return $this->allows(
            $user,
            PermissionName::AssistanceDelete,
            $this->belongsToUserDepartment($user, $assistance),
        );
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Assistance $assistance): bool
    {
        return $this->update($user, $assistance);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Assistance $assistance): bool
    {
        return $this->delete($user, $assistance);
    }

    /**
     * Determine whether the user can assign the assistance.
     */
    public function assign(User $user, Assistance $assistance): bool
    {
        if (! $this->allows(
            $user,
            PermissionName::AssistanceAssign,
            $this->belongsToUserDepartment($user, $assistance),
        )) {
            return false;
        }

        $task = $assistance->currentTask();

        if ($task !== null) {
            return $task->assigned_to_id === null
                || (int) $task->assigned_to_id === (int) $user->id
                || $user->can(PermissionName::DepartmentSupervise->value);
        }

        return $assistance->currentWorkflowStep()?->assigned_to_id === null;
    }

    /**
     * Determine whether the user can claim the assistance.
     */
    public function claim(User $user, Assistance $assistance): bool
    {
        if (! $this->allows(
            $user,
            PermissionName::AssistanceClaim,
            $this->belongsToUserDepartment($user, $assistance),
        )) {
            return false;
        }

        $task = $assistance->currentTask();

        if ($task !== null) {
            return $task->assigned_to_id === null;
        }

        $step = $assistance->currentWorkflowStep();

        if ($step?->assigned_to_id === null) {
            return true;
        }

        return (int) $step->assigned_to_id === (int) $user->id;
    }

    /**
     * Determine whether the user can advance the assistance status.
     */
    public function advance(User $user, Assistance $assistance): bool
    {
        if (! $this->allows(
            $user,
            PermissionName::AssistanceAdvance,
            $this->belongsToUserDepartment($user, $assistance),
        )) {
            return false;
        }

        $task = $assistance->currentTask();

        if ($task !== null && $task->assigned_to_id !== null) {
            return (int) $task->assigned_to_id === (int) $user->id;
        }

        if ($assistance->assigned_to_id !== null) {
            return (int) $assistance->assigned_to_id === (int) $user->id;
        }

        $step = $assistance->currentWorkflowStep();

        if ($step?->assigned_to_id !== null) {
            return (int) $step->assigned_to_id === (int) $user->id;
        }

        return true;
    }

    private function belongsToUserDepartment(User $user, Assistance $assistance): bool
    {
        $assistance->loadMissing('program');

        return $user->department_id === $assistance->program?->department_id;
    }
}
