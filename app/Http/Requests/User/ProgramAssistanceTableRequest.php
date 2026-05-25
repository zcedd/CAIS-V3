<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgramAssistanceTableRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 20, 25, 30, 40, 50])],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string', 'max:255'],
            'mode' => ['nullable', 'array'],
            'mode.*' => ['string', 'max:255'],
            'sort' => [
                'nullable',
                'string',
                Rule::in([
                    'id',
                    'cais_number',
                    'beneficiary_name',
                    'mode_of_request',
                    'status',
                    'request_sub_status_recorded_at',
                    'date_requested',
                    'date_verified',
                    'date_delivered',
                    'date_denied',
                    'remark',
                ]),
            ],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    public function sort(): string
    {
        return $this->validated('sort') ?? 'id';
    }

    public function direction(): string
    {
        return $this->validated('direction') ?? 'desc';
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 15);
    }

    public function search(): string
    {
        return trim($this->validated('search') ?? '');
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
    public function modes(): array
    {
        return array_values($this->validated('mode') ?? []);
    }
}
