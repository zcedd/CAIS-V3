<?php

namespace App\Http\Requests\User\ItemStock;

use App\Enums\StockMovementType;
use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAdjustmentRequest extends FormRequest
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
        $itemId = $item instanceof Item ? $item->id : 0;

        return [
            'stock_lot_id' => [
                'required',
                'integer',
                Rule::exists('stock_lots', 'id')->where('item_id', $itemId),
            ],
            'type' => [
                'required',
                Rule::enum(StockMovementType::class)->only([
                    StockMovementType::AdjustmentIn,
                    StockMovementType::AdjustmentOut,
                ]),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'stock_lot_id' => 'lot',
            'type' => 'adjustment type',
            'quantity' => 'quantity',
            'reason' => 'reason',
        ];
    }
}
