<?php

namespace App\Http\Requests\User\Program;

use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('viewAny', [Program::class, $this->route('department')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'array'],
            'type.*' => ['string', Rule::in(['individual', 'organization'])],
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in(['open', 'closed'])],
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
}
