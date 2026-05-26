<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgramAssistanceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $program = $this->route('program');

        $programItemIds = $program->item()->pluck('items.id')->all();

        return [
            'beneficiary_id' => [
                'required',
                'integer',
                Rule::exists('beneficiaries', 'id'),
            ],
            'mode_of_request_id' => [
                'required',
                'integer',
                Rule::exists('mode_of_requests', 'id'),
            ],
            'remark' => ['nullable', 'string'],
            'item_details' => ['required', 'array', 'min:1'],
            'item_details.*.item_id' => ['required', 'integer', Rule::in($programItemIds)],
            'item_details.*.quantity' => ['required', 'integer', 'min:1'],
            'item_details.*.specification' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'beneficiary_id' => 'beneficiary',
            'mode_of_request_id' => 'mode of request',
            'remark' => 'remark',
            'item_details' => 'items',
            'item_details.*.item_id' => 'item',
            'item_details.*.quantity' => 'item quantity',
            'item_details.*.specification' => 'item specification',
        ];
    }
}
