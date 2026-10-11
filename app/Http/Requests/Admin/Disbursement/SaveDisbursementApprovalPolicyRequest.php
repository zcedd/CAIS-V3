<?php

namespace App\Http\Requests\Admin\Disbursement;

use App\Enums\DisbursementApprovalMode;
use App\Enums\DisbursementApprovalStrategy;
use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDisbursementApprovalPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::DisbursementPolicyManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'approval_mode' => ['nullable', Rule::enum(DisbursementApprovalMode::class)],
            'strategy' => ['required', Rule::enum(DisbursementApprovalStrategy::class)],
            'allow_partial_finalization' => ['sometimes', 'boolean'],
            'allow_amount_modification' => ['sometimes', 'boolean'],
            'allow_self_approval' => ['sometimes', 'boolean'],
            'reschedule_requires_reapproval' => ['sometimes', 'boolean'],
            'allow_resubmit_after_reject' => ['sometimes', 'boolean'],
            'block_active_batch_reuse' => ['sometimes', 'boolean'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'stages' => ['required', 'array', 'min:1'],
            'stages.*.name' => ['required', 'string', 'max:255'],
            'stages.*.sequence' => ['required', 'integer', 'min:1'],
            'stages.*.role_name' => ['nullable', 'string', 'max:255'],
            'stages.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
