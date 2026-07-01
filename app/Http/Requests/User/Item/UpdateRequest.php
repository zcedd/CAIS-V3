<?php

namespace App\Http\Requests\User\Item;

use App\Models\Department;
use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRequest extends FormRequest
{
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
            'item_unit_measurement_id' => [
                'required',
                'integer',
                Rule::exists('item_unit_measurements', 'id'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $department = $this->route('department');
            $item = $this->route('item');

            if (! $department instanceof Department || ! $item instanceof Item) {
                return;
            }

            if ($item->department_id !== $department->id) {
                $validator->errors()->add('item', 'The selected item does not belong to this department.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'item name',
            'item_unit_measurement_id' => 'unit of measurement',
        ];
    }
}
