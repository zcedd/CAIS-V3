<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\PermissionName;

trait ChecksDepartmentPermission
{
    protected function allows(User $user, PermissionName $permission, bool $inDepartment): bool
    {
        return $inDepartment && $user->can($permission->value);
    }
}
