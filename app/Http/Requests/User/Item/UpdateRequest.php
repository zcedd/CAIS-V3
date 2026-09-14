<?php

namespace App\Http\Requests\User\Item;

use App\Http\Requests\User\Concerns\ValidatesItemKind;
use App\Models\Department;
use App\Models\Item;
use App\Support\ItemKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRequest extends FormRequest
{
    use ValidatesItemKind;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('item'));
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
            'kind' => ['required', 'string', Rule::in(ItemKind::values())],
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
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $department = $this->route('department');
                $item = $this->route('item');

                if (! $department instanceof Department || ! $item instanceof Item) {
                    return;
                }

                if ($item->department_id !== $department->id) {
                    $validator->errors()->add('item', 'The selected item does not belong to this department.');
                }

                $this->assertCashUsesPhpUnit($validator);
                $this->assertCannotChangeStockedGoodsKind($validator, $item);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'item name',
            'kind' => 'kind',
            'item_unit_measurement_id' => 'unit of measurement',
            'unspsc_code_id' => 'UNSPSC classification',
            'is_perishable' => 'perishable',
            'low_stock_threshold' => 'low stock threshold',
        ];
    }
}
