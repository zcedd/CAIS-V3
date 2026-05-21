<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgramIndexRequest extends FormRequest
{
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
