<?php

namespace App\Policies\Concerns;

use App\Enums\PermissionName;
use App\Models\User;

trait ChecksDepartmentPermission
{
    protected function allows(User $user, PermissionName $permission, bool $inDepartment): bool
    {
        return $inDepartment && $user->can($permission->value);
    }
}
