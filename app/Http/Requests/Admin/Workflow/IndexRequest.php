<?php

namespace App\Http\Requests\Admin\Workflow;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

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
            'department' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function departmentSlug(): string
    {
        return trim((string) ($this->validated('department') ?? ''));
    }
}
