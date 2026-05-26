<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgramAssistanceStatusRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
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
