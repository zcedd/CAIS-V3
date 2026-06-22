<?php

namespace App\Http\Requests\User\Assistance;

use App\Models\Assistance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', [Assistance::class, $this->route('program')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
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
            'recorded_at' => ['required', 'date'],
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
            'recorded_at' => 'recorded at',
            'remark' => 'remark',
            'item_details' => 'items',
            'item_details.*.item_id' => 'item',
            'item_details.*.quantity' => 'item quantity',
            'item_details.*.specification' => 'item specification',
        ];
    }
}
