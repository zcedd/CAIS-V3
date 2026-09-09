<?php

namespace App\Http\Requests\Admin\Workflow;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

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
            'department' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function departmentSlug(): string
    {
        return trim((string) ($this->validated('department') ?? ''));
    }
}
