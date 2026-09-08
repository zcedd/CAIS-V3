<?php

namespace App\Policies;

use App\Models\Assistance;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;

class AssistancePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Department $department): bool
    {
        return $user->department_id === $department->id;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Assistance $assistance): bool
    {
        return $this->belongsToUserDepartment($user, $assistance);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Program $program): bool
    {
        return $user->department_id === $program->department_id
            && $program->isEncodable();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Assistance $assistance): bool
    {
        return $this->belongsToUserDepartment($user, $assistance);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Assistance $assistance): bool
    {
        return $this->belongsToUserDepartment($user, $assistance);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Assistance $assistance): bool
    {
        return $this->belongsToUserDepartment($user, $assistance);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Assistance $assistance): bool
    {
        return $this->update($user, $assistance);
    }

    /**
     * Determine whether the user can assign the assistance.
     */
    public function assign(User $user, Assistance $assistance): bool
    {
        if (! $this->belongsToUserDepartment($user, $assistance)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $assistance->currentWorkflowStep()?->assigned_to_id === null;
    }

    /**
     * Determine whether the user can claim the assistance.
     */
    public function claim(User $user, Assistance $assistance): bool
    {
        if (! $this->belongsToUserDepartment($user, $assistance)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
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
        if (! $this->belongsToUserDepartment($user, $assistance)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $step = $assistance->currentWorkflowStep();

        if ($step?->assigned_to_id === null) {
            return true;
        }

        return (int) $assistance->assigned_to_id === (int) $user->id
            || (int) $step->assigned_to_id === (int) $user->id;
    }

    private function belongsToUserDepartment(User $user, Assistance $assistance): bool
    {
        $assistance->loadMissing('program');

        return $user->department_id === $assistance->program?->department_id;
    }
}
