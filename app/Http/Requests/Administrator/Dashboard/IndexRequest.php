<?php

namespace App\Http\Requests\Administrator\Dashboard;

use App\Enums\PermissionName;
use App\Http\Requests\Executive\Dashboard\IndexRequest as ExecutiveDashboardIndexRequest;

class IndexRequest extends ExecutiveDashboardIndexRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::ProgramEndorse->value) ?? false;
    }
}
