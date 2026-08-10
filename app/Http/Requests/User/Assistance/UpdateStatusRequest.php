<?php

namespace App\Http\Requests\User\Assistance;

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\RequestSubStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        /** @var Assistance $assistance */
        $assistance = $this->route('assistance');

        return [
            'request_sub_status_id' => [
                'required',
                'integer',
                Rule::exists('request_sub_statuses', 'id'),
            ],
            'recorded_at' => ['required', 'date'],
            'remark' => ['nullable', 'string'],
            'delivered_items' => [
                Rule::requiredIf(fn (): bool => $this->isDeliveredSubStatus()),
                'array',
                'min:1',
            ],
            'delivered_items.*.assistance_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('assistance_item', 'id')
                    ->where('assistance_id', $assistance->id)
                    ->where('is_received', 0),
            ],
            'delivered_items.*.quantity' => ['required', 'integer', 'min:1'],
            'delivered_items.*.specification' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->isDeliveredSubStatus()) {
                    return;
                }

                /** @var Assistance $assistance */
                $assistance = $this->route('assistance');

                foreach ($this->input('delivered_items', []) as $index => $deliveredItem) {
                    $assistanceItemId = $deliveredItem['assistance_item_id'] ?? null;

                    if ($assistanceItemId === null) {
                        continue;
                    }

                    $assistanceItem = AssistanceItem::query()
                        ->where('assistance_id', $assistance->id)
                        ->whereKey($assistanceItemId)
                        ->first();

                    if ($assistanceItem === null) {
                        continue;
                    }

                    $deliveredQuantity = (int) ($deliveredItem['quantity'] ?? 0);

                    if (
                        $assistanceItem->quantity !== null
                        && $deliveredQuantity > $assistanceItem->quantity
                    ) {
                        $validator->errors()->add(
                            "delivered_items.{$index}.quantity",
                            'The delivered quantity cannot exceed the requested quantity.',
                        );
                    }
                }
            },
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
            'delivered_items' => 'delivered items',
            'delivered_items.*.assistance_item_id' => 'delivered item',
            'delivered_items.*.quantity' => 'delivered quantity',
            'delivered_items.*.specification' => 'delivered specification',
        ];
    }

    private function isDeliveredSubStatus(): bool
    {
        $subStatusId = $this->integer('request_sub_status_id');

        if ($subStatusId === 0) {
            return false;
        }

        return RequestSubStatus::query()
            ->whereKey($subStatusId)
            ->whereHas('requestStatus', fn ($query) => $query->where('name', 'Delivered'))
            ->exists();
    }
}
