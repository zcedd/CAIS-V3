<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\AssistanceRequestSubStatus;
use App\Models\RequestSubStatus;
use App\Services\User\AssistanceDocumentService;
use Illuminate\Support\Carbon;

class UpdateProgramAssistanceStatus
{
    public function __construct(
        private AssistanceDocumentService $assistanceDocumentService,
    ) {}

    /**
     * @param  array{
     *     request_sub_status_id: int,
     *     recorded_at: string,
     *     remark?: string|null,
     *     delivered_items?: list<array{
     *         assistance_item_id: int,
     *         quantity: int,
     *         specification?: string|null
     *     }>|null
     * }  $validated
     */
    public function __invoke(Assistance $assistance, array $validated): Assistance
    {
        $subStatus = RequestSubStatus::query()
            ->with('requestStatus:id,name')
            ->findOrFail($validated['request_sub_status_id']);

        $this->assistanceDocumentService->assertCompleteForSubStatus($assistance, $subStatus);

        $recordedAt = Carbon::parse($validated['recorded_at']);

        AssistanceRequestSubStatus::query()->create([
            'assistance_id' => $assistance->id,
            'request_sub_status_id' => $validated['request_sub_status_id'],
            'remark' => $validated['remark'] ?? null,
            'recorded_at' => $recordedAt,
        ]);

        foreach ($validated['delivered_items'] ?? [] as $deliveredItem) {
            $assistanceItem = AssistanceItem::query()
                ->where('assistance_id', $assistance->id)
                ->whereKey($deliveredItem['assistance_item_id'])
                ->firstOrFail();

            $deliveredQuantity = $deliveredItem['quantity'];
            $pendingQuantity = $assistanceItem->quantity;
            $remainingQuantity = $pendingQuantity !== null
                ? $pendingQuantity - $deliveredQuantity
                : 0;

            if ($remainingQuantity > 0) {
                $assistanceItem->update([
                    'quantity' => $remainingQuantity,
                ]);

                AssistanceItem::query()->create([
                    'assistance_id' => $assistance->id,
                    'item_id' => $assistanceItem->item_id,
                    'quantity' => $deliveredQuantity,
                    'specification' => $deliveredItem['specification'] ?? null,
                    'is_received' => true,
                ]);
            } else {
                $assistanceItem->update([
                    'quantity' => $deliveredQuantity,
                    'specification' => $deliveredItem['specification'] ?? $assistanceItem->specification,
                    'is_received' => true,
                ]);
            }
        }

        // Milestone dates and current status are synced from status history via
        // AssistanceRequestSubStatus model events (SyncAssistanceCurrentStatus).

        return $assistance->refresh();
    }
}
