<?php

namespace App\Http\Requests\User\Assistance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ExportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('downloadAssistance', $this->route('program'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
            'format' => ['nullable', 'string', Rule::in(['xlsx', 'csv'])],
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

    public function exportFormat(): string
    {
        return $this->validated('format') ?? 'xlsx';
    }
}
