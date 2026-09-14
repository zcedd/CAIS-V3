<?php

namespace App\Http\Requests\Admin\User;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', User::class);
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
            'role' => ['nullable', 'array'],
            'role.*' => [Rule::enum(RoleName::class)],
            'sort' => ['nullable', 'string', Rule::in(['name', 'email', 'department'])],
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
    public function roles(): array
    {
        return array_values($this->validated('role') ?? []);
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
