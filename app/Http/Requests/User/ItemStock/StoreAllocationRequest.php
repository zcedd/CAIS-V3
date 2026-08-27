<?php

namespace App\Http\Requests\User\ItemStock;

use App\Models\Item;
use App\Support\StockMovementType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof Item && Gate::allows('update', $item);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $item = $this->route('item');
        $departmentId = $item instanceof Item ? $item->department_id : 0;

        return [
            'program_id' => [
                'required',
                'integer',
                Rule::exists('programs', 'id')->where('department_id', $departmentId),
            ],
            'type' => [
                'required',
                'string',
                Rule::in([StockMovementType::Allocate, StockMovementType::Deallocate]),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'program_id' => 'program',
            'type' => 'allocation type',
            'quantity' => 'quantity',
            'reason' => 'reason',
        ];
    }
}
