<?php

namespace App\Http\Requests\User\Concerns;

use App\Enums\PermissionName;
use App\Models\User;

trait AuthorizesProgramWorkflowAssignment
{
    protected function canAssignWorkflow(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can(PermissionName::WorkflowAssign->value);
    }
}
