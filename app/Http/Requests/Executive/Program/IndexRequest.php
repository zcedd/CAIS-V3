<?php

namespace App\Http\Requests\Executive\Program;

use App\Enums\PermissionName;
use App\Enums\ProgramApprovalStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::ProgramApprove->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([8, 12, 16, 20, 24, 32, 48])],
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'array'],
            'type.*' => ['string', Rule::in(['individual', 'organization'])],
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in(['open', 'closed'])],
            'approval' => ['nullable', 'array'],
            'approval.*' => ['string', Rule::enum(ProgramApprovalStatus::class)],
            'department' => ['nullable', 'array'],
            'department.*' => [
                'integer',
                Rule::exists('departments', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function search(): string
    {
        return trim($this->validated('search') ?? '');
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_values($this->validated('type') ?? []);
    }

    /**
     * @return list<string>
     */
    public function statuses(): array
    {
        return array_values($this->validated('status') ?? []);
    }

    /**
     * @return list<string>
     */
    public function approvals(): array
    {
        return array_values($this->validated('approval') ?? []);
    }

    /**
     * @return list<int>
     */
    public function departments(): array
    {
        return array_map(intval(...), array_values($this->validated('department') ?? []));
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 12);
    }
}
