<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\AssistanceRequestSubStatus;
use App\Models\RequestSubStatus;
use App\Models\User;
use App\Services\User\AssistanceDocumentService;
use App\Services\User\StockLedgerService;
use App\Support\AssistanceItemOrigin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateProgramAssistanceStatus
{
    public function __construct(
        private AssistanceDocumentService $assistanceDocumentService,
        private StockLedgerService $stockLedgerService,
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
     *     }>|null,
     *     extra_items?: list<array{
     *         origin: string,
     *         item_id: int,
     *         quantity: int,
     *         fulfillment_reason: string,
     *         specification?: string|null,
     *         substituted_for_assistance_item_id?: int|null
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
        $user = Auth::user();
        $assistance->loadMissing('program');

        return DB::transaction(function () use ($assistance, $validated, $recordedAt, $subStatus, $user): Assistance {
            AssistanceRequestSubStatus::query()->create([
                'assistance_id' => $assistance->id,
                'request_sub_status_id' => $validated['request_sub_status_id'],
                'remark' => $validated['remark'] ?? null,
                'recorded_at' => $recordedAt,
            ]);

            $releasedItems = [];

            foreach ($validated['delivered_items'] ?? [] as $deliveredItem) {
                $releasedItems[] = $this->releaseRequestedItem($assistance, $deliveredItem);
            }

            foreach ($validated['extra_items'] ?? [] as $extraItem) {
                $releasedItems[] = $this->releaseUnrequestedItem($assistance, $extraItem, $recordedAt);
            }

            $program = $assistance->program;

            if ($user instanceof User && $program !== null) {
                foreach ($releasedItems as $releasedItem) {
                    $this->stockLedgerService->issue($releasedItem, $program, $user);
                }
            }

            if ($user instanceof User && $subStatus->requestStatus?->name === 'Denied') {
                $this->stockLedgerService->restoreForAssistance($assistance, $user);
            }

            // Milestone dates and current status are synced from status history via
            // AssistanceRequestSubStatus model events (SyncAssistanceCurrentStatus).

            return $assistance->refresh();
        });
    }

    /**
     * Hand over part or all of a requested line. Partial releases split the row so the
     * outstanding request stays visible and the requested quantity is never rewritten.
     *
     * @param  array{assistance_item_id: int, quantity: int, specification?: string|null}  $deliveredItem
     */
    private function releaseRequestedItem(Assistance $assistance, array $deliveredItem): AssistanceItem
    {
        $assistanceItem = AssistanceItem::query()
            ->where('assistance_id', $assistance->id)
            ->whereKey($deliveredItem['assistance_item_id'])
            ->firstOrFail();

        $releasedQuantity = (int) $deliveredItem['quantity'];
        $outstandingQuantity = (int) ($assistanceItem->quantity ?? 0);
        $remainingQuantity = $outstandingQuantity - $releasedQuantity;

        if ($remainingQuantity > 0) {
            $assistanceItem->update([
                'quantity' => $remainingQuantity,
                'requested_quantity' => $remainingQuantity,
            ]);

            return AssistanceItem::query()->create([
                'assistance_id' => $assistance->id,
                'item_id' => $assistanceItem->item_id,
                'origin' => AssistanceItemOrigin::Requested,
                'quantity' => $releasedQuantity,
                'requested_quantity' => $releasedQuantity,
                'specification' => $deliveredItem['specification'] ?? null,
                'is_received' => true,
            ]);
        }

        $assistanceItem->update([
            'quantity' => $releasedQuantity,
            'requested_quantity' => $releasedQuantity,
            'specification' => $deliveredItem['specification'] ?? $assistanceItem->specification,
            'is_received' => true,
        ]);

        return $assistanceItem->refresh();
    }

    /**
     * Record something handed over that was never on the request: an additional item, or a
     * substitute that retires the requested line it replaces without deleting it.
     *
     * @param  array{
     *     origin: string,
     *     item_id: int,
     *     quantity: int,
     *     fulfillment_reason: string,
     *     specification?: string|null,
     *     substituted_for_assistance_item_id?: int|null
     * }  $extraItem
     */
    private function releaseUnrequestedItem(
        Assistance $assistance,
        array $extraItem,
        Carbon $recordedAt,
    ): AssistanceItem {
        $substitutedForId = null;

        if ($extraItem['origin'] === AssistanceItemOrigin::Substitute) {
            $substitutedItem = AssistanceItem::query()
                ->where('assistance_id', $assistance->id)
                ->whereKey($extraItem['substituted_for_assistance_item_id'])
                ->firstOrFail();

            $substitutedItem->update(['substituted_at' => $recordedAt]);

            $substitutedForId = $substitutedItem->id;
        }

        return AssistanceItem::query()->create([
            'assistance_id' => $assistance->id,
            'item_id' => $extraItem['item_id'],
            'origin' => $extraItem['origin'],
            'quantity' => $extraItem['quantity'],
            'requested_quantity' => 0,
            'substituted_for_assistance_item_id' => $substitutedForId,
            'fulfillment_reason' => $extraItem['fulfillment_reason'],
            'specification' => $extraItem['specification'] ?? null,
            'is_received' => true,
        ]);
    }
}
