<?php

namespace App\Http\Requests\Admin\Workflow;

use App\Enums\PermissionName;
use App\Enums\WorkflowStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::WorkflowViewAny->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 20, 25, 30, 40, 50])],
            'search' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(WorkflowStatus::class)],
            'sort' => ['nullable', 'string', Rule::in(['name', 'code', 'version', 'status', 'department'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    public function search(): string
    {
        return trim($this->validated('search') ?? '');
    }

    public function departmentId(): ?int
    {
        $departmentId = $this->validated('department_id');

        return $departmentId === null ? null : (int) $departmentId;
    }

    /**
     * @return list<string>
     */
    public function statuses(): array
    {
        return array_values($this->validated('status') ?? []);
    }

    public function sort(): string
    {
        return $this->validated('sort') ?? 'name';
    }

    public function direction(): string
    {
        return $this->validated('direction') ?? 'asc';
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 15);
    }
}
