<?php

namespace App\Http\Requests\User\Assistance;

use App\Http\Requests\User\Concerns\EnsuresAssistanceBelongsToProgram;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Item;
use App\Models\Program;
use App\Models\RequestSubStatus;
use App\Services\User\StockLedgerService;
use App\Support\AssistanceItemOrigin;
use App\Support\RequestStatusCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

class UpdateStatusRequest extends FormRequest
{
    use EnsuresAssistanceBelongsToProgram;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $this->ensureAssistanceBelongsToProgram();

        return Gate::allows('update', $this->assistance);
    }

    /**
     * The delivery drawer auto-selects the only outstanding requested line. A substitute of
     * that same line is the more specific intent, so drop it from delivered_items first.
     */
    protected function prepareForValidation(): void
    {
        $deliveredItems = $this->input('delivered_items');
        $extraItems = $this->input('extra_items');

        if (! is_array($deliveredItems) || ! is_array($extraItems)) {
            return;
        }

        $substitutedIds = collect($extraItems)
            ->filter(static fn (mixed $row): bool => is_array($row)
                && ($row['origin'] ?? null) === AssistanceItemOrigin::Substitute)
            ->map(static fn (array $row): int => (int) ($row['substituted_for_assistance_item_id'] ?? 0))
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->all();

        if ($substitutedIds === []) {
            return;
        }

        $this->merge([
            'delivered_items' => collect($deliveredItems)
                ->filter(static fn (mixed $row): bool => is_array($row)
                    && ! in_array((int) ($row['assistance_item_id'] ?? 0), $substitutedIds, true))
                ->values()
                ->all(),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Program $program */
        $program = $this->route('program');

        $programItemIds = $program->item()->pluck('items.id')->all();
        $notDelivered = fn (): bool => ! $this->isDeliveredSubStatus();

        return [
            'request_sub_status_id' => [
                'required',
                'integer',
                Rule::exists('request_sub_statuses', 'id'),
            ],
            'assigned_to_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'recorded_at' => ['required', 'date'],
            'remark' => ['nullable', 'string'],
            'delivered_items' => [Rule::prohibitedIf($notDelivered), 'array'],
            'delivered_items.*.assistance_item_id' => [
                'required',
                'integer',
                'distinct',
                $this->awaitingReleaseRule(),
            ],
            'delivered_items.*.quantity' => ['required', 'integer', 'min:1'],
            'delivered_items.*.specification' => ['nullable', 'string', 'max:255'],
            'extra_items' => [Rule::prohibitedIf($notDelivered), 'array'],
            'extra_items.*.origin' => [
                'required',
                'string',
                Rule::in(AssistanceItemOrigin::unrequestedValues()),
            ],
            'extra_items.*.item_id' => ['required', 'integer', Rule::in($programItemIds)],
            'extra_items.*.quantity' => ['required', 'integer', 'min:1'],
            'extra_items.*.specification' => ['nullable', 'string', 'max:255'],
            'extra_items.*.fulfillment_reason' => ['required', 'string', 'max:255'],
            'extra_items.*.substituted_for_assistance_item_id' => [
                'nullable',
                'integer',
                'required_if:extra_items.*.origin,'.AssistanceItemOrigin::Substitute,
                'prohibited_unless:extra_items.*.origin,'.AssistanceItemOrigin::Substitute,
                $this->awaitingReleaseRule(),
            ],
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

                $deliveredItems = $this->input('delivered_items', []);
                $extraItems = $this->input('extra_items', []);

                if ($deliveredItems === [] && $extraItems === []) {
                    $validator->errors()->add(
                        'delivered_items',
                        'Select at least one requested item to release, or add an item that was actually released.',
                    );

                    return;
                }

                $remainingByAssistanceItem = $this->remainingQuantities();
                $fullyReleasedIds = [];

                foreach ($deliveredItems as $index => $deliveredItem) {
                    $assistanceItemId = (int) ($deliveredItem['assistance_item_id'] ?? 0);
                    $remainingQuantity = $remainingByAssistanceItem[$assistanceItemId] ?? null;

                    if ($remainingQuantity === null) {
                        continue;
                    }

                    $deliveredQuantity = (int) ($deliveredItem['quantity'] ?? 0);

                    if ($deliveredQuantity > $remainingQuantity) {
                        $validator->errors()->add(
                            "delivered_items.{$index}.quantity",
                            'The released quantity cannot exceed the remaining requested quantity. Record the excess as an additional item instead.',
                        );

                        continue;
                    }

                    if ($deliveredQuantity === $remainingQuantity) {
                        $fullyReleasedIds[] = $assistanceItemId;
                    }
                }

                $substitutedIds = [];

                foreach ($extraItems as $index => $extraItem) {
                    if (($extraItem['origin'] ?? null) !== AssistanceItemOrigin::Substitute) {
                        continue;
                    }

                    $targetId = (int) ($extraItem['substituted_for_assistance_item_id'] ?? 0);

                    if ($targetId === 0) {
                        continue;
                    }

                    if (in_array($targetId, $substitutedIds, true)) {
                        $validator->errors()->add(
                            "extra_items.{$index}.substituted_for_assistance_item_id",
                            'This requested item is already being substituted by another line in this update.',
                        );

                        continue;
                    }

                    if (in_array($targetId, $fullyReleasedIds, true)) {
                        $validator->errors()->add(
                            "extra_items.{$index}.substituted_for_assistance_item_id",
                            'This requested item is fully released in this update, so it cannot also be substituted.',
                        );

                        continue;
                    }

                    $substitutedIds[] = $targetId;
                }

                $this->assertProgramStock($validator, $deliveredItems, $extraItems);
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
            'delivered_items' => 'released items',
            'delivered_items.*.assistance_item_id' => 'requested item',
            'delivered_items.*.quantity' => 'released quantity',
            'delivered_items.*.specification' => 'released specification',
            'extra_items' => 'additional or substitute items',
            'extra_items.*.origin' => 'line type',
            'extra_items.*.item_id' => 'item',
            'extra_items.*.quantity' => 'released quantity',
            'extra_items.*.specification' => 'specification',
            'extra_items.*.fulfillment_reason' => 'reason',
            'extra_items.*.substituted_for_assistance_item_id' => 'substituted requested item',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $deliveredItems
     * @param  list<array<string, mixed>>  $extraItems
     */
    private function assertProgramStock(Validator $validator, array $deliveredItems, array $extraItems): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        /** @var Program $program */
        $program = $this->route('program');
        $neededByItemId = [];

        $deliveredAssistanceItemIds = collect($deliveredItems)
            ->map(static fn (array $row): int => (int) ($row['assistance_item_id'] ?? 0))
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->all();

        $itemIdByAssistanceItemId = $deliveredAssistanceItemIds === []
            ? []
            : AssistanceItem::query()
                ->whereIn('id', $deliveredAssistanceItemIds)
                ->pluck('item_id', 'id')
                ->map(static fn ($itemId): int => (int) $itemId)
                ->all();

        foreach ($deliveredItems as $deliveredItem) {
            $assistanceItemId = (int) ($deliveredItem['assistance_item_id'] ?? 0);
            $itemId = $itemIdByAssistanceItemId[$assistanceItemId] ?? 0;

            if ($itemId === 0) {
                continue;
            }

            $neededByItemId[$itemId] = ($neededByItemId[$itemId] ?? 0) + (int) ($deliveredItem['quantity'] ?? 0);
        }

        foreach ($extraItems as $extraItem) {
            $itemId = (int) ($extraItem['item_id'] ?? 0);

            if ($itemId === 0) {
                continue;
            }

            $neededByItemId[$itemId] = ($neededByItemId[$itemId] ?? 0) + (int) ($extraItem['quantity'] ?? 0);
        }

        if ($neededByItemId === []) {
            return;
        }

        $remainingByItemId = app(StockLedgerService::class)->remainingByItemId($program);
        $catalogItems = Item::query()
            ->whereIn('id', array_keys($neededByItemId))
            ->get(['id', 'name', 'kind'])
            ->keyBy('id');

        foreach ($neededByItemId as $itemId => $needed) {
            $item = $catalogItems->get($itemId);

            if (! $item instanceof Item || ! $item->tracksInventory()) {
                continue;
            }

            $remaining = $remainingByItemId[$itemId] ?? 0;

            if ($needed <= $remaining) {
                continue;
            }

            $validator->errors()->add(
                'delivered_items',
                "Not enough allocated stock of {$item->name} for this program. Remaining allocation is {$remaining}.",
            );
        }
    }

    /**
     * Only requested lines that are still owed may be released or substituted.
     */
    private function awaitingReleaseRule(): Exists
    {
        /** @var Assistance $assistance */
        $assistance = $this->route('assistance');

        return Rule::exists('assistance_item', 'id')
            ->where('assistance_id', $assistance->id)
            ->where('origin', AssistanceItemOrigin::Requested)
            ->where('is_received', 0)
            ->whereNull('substituted_at')
            ->whereNull('deleted_at');
    }

    /**
     * @return array<int, int>
     */
    private function remainingQuantities(): array
    {
        /** @var Assistance $assistance */
        $assistance = $this->route('assistance');

        return AssistanceItem::query()
            ->where('assistance_id', $assistance->id)
            ->awaitingRelease()
            ->pluck('quantity', 'id')
            ->map(static fn ($quantity): int => (int) $quantity)
            ->all();
    }

    private function isDeliveredSubStatus(): bool
    {
        $subStatusId = $this->integer('request_sub_status_id');

        if ($subStatusId === 0) {
            return false;
        }

        return RequestSubStatus::query()
            ->whereKey($subStatusId)
            ->whereHas('requestStatus', fn ($query) => $query
                ->where('code', RequestStatusCode::Delivered->value)
                ->orWhere('name', 'Delivered'))
            ->exists();
    }
}
