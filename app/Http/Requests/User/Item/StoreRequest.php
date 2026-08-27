<?php

namespace App\Http\Requests\User\Item;

use App\Models\Item;
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
        return Gate::allows('create', [Item::class, $this->route('department')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'item_unit_measurement_id' => [
                'required',
                'integer',
                Rule::exists('item_unit_measurements', 'id'),
            ],
            'unspsc_code_id' => ['nullable', 'integer', Rule::exists('unspsc_codes', 'id')],
            'is_perishable' => ['sometimes', 'boolean'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('is_perishable')) {
            $this->merge([
                'is_perishable' => $this->boolean('is_perishable'),
            ]);
        }

        if ($this->input('unspsc_code_id') === '') {
            $this->merge(['unspsc_code_id' => null]);
        }

        if ($this->input('low_stock_threshold') === '') {
            $this->merge(['low_stock_threshold' => null]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'item name',
            'item_unit_measurement_id' => 'unit of measurement',
            'unspsc_code_id' => 'UNSPSC classification',
            'is_perishable' => 'perishable',
            'low_stock_threshold' => 'low stock threshold',
        ];
    }
}
