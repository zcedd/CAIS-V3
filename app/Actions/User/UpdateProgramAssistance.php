<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceItem;

class UpdateProgramAssistance
{
    /**
     * @param  array{
     *     beneficiary_id: int,
     *     mode_of_request_id: int,
     *     remark?: string|null,
     *     item_details: list<array{
     *         item_id: int,
     *         quantity: int,
     *         specification?: string|null
     *     }>
     * }  $validated
     */
    public function __invoke(Assistance $assistance, array $validated): Assistance
    {
        $assistance->update([
            'beneficiary_id' => $validated['beneficiary_id'],
            'mode_of_request_id' => $validated['mode_of_request_id'],
            'remark' => $validated['remark'] ?? null,
        ]);

        AssistanceItem::query()
            ->where('assistance_id', $assistance->id)
            ->delete();

        foreach ($validated['item_details'] as $itemDetail) {
            AssistanceItem::query()->create([
                'assistance_id' => $assistance->id,
                'item_id' => $itemDetail['item_id'],
                'quantity' => $itemDetail['quantity'],
                'specification' => $itemDetail['specification'] ?? null,
                'is_received' => false,
            ]);
        }

        return $assistance->refresh();
    }
}
