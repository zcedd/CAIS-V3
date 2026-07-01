<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Item;
use App\Models\User;

class ItemPolicy
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
    public function view(User $user, Item $item): bool
    {
        return $user->department_id === $item->department_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Department $department): bool
    {
        return $user->department_id === $department->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Item $item): bool
    {
        return $user->department_id === $item->department_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Item $item): bool
    {
        return $user->department_id === $item->department_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Item $item): bool
    {
        return $user->department_id === $item->department_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Item $item): bool
    {
        return $user->department_id === $item->department_id;
    }
}
