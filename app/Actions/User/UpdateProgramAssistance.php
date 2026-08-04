<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Services\User\ProgramFieldService;

class UpdateProgramAssistance
{
    public function __construct(
        private ProgramFieldService $programFieldService,
    ) {}

    /**
     * @param  array{
     *     beneficiary_id: int,
     *     mode_of_request_id: int,
     *     remark?: string|null,
     *     item_details: list<array{
     *         item_id: int,
     *         quantity: int,
     *         specification?: string|null
     *     }>,
     *     field_values?: list<array{
     *         program_field_id: int,
     *         value?: string|null
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

        $this->programFieldService->syncValuesForAssistance(
            $assistance,
            $validated['field_values'] ?? [],
        );

        return $assistance->refresh();
    }
}
