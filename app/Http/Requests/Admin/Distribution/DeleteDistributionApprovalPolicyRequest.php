<?php

namespace App\Http\Requests\Admin\Distribution;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class DeleteDistributionApprovalPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::DistributionPolicyManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
