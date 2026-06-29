<?php

namespace App\Http\Requests\User\Assistance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->assistance);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_sub_status_id' => [
                'required',
                'integer',
                Rule::exists('request_sub_statuses', 'id'),
            ],
            'recorded_at' => ['required', 'date'],
            'remark' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'request_sub_status_id' => 'status',
            'recorded_at' => 'recorded at',
            'remark' => 'remark',
        ];
    }
}
