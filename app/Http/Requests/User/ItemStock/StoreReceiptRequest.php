<?php

namespace App\Http\Requests\User\ItemStock;

use App\Models\Item;
use App\Support\StockMovementType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreReceiptRequest extends FormRequest
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
        return [
            'quantity' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', Rule::in(StockMovementType::receiptValues())],
            'batch_number' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
            'received_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quantity' => 'quantity',
            'type' => 'receipt type',
            'batch_number' => 'batch number',
            'expires_at' => 'expiry date',
            'received_at' => 'received at',
            'reason' => 'reason',
        ];
    }
}
