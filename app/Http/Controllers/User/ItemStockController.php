<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ItemStock\ShowRequest;
use App\Http\Requests\User\ItemStock\StoreAdjustmentRequest;
use App\Http\Requests\User\ItemStock\StoreAllocationRequest;
use App\Http\Requests\User\ItemStock\StoreReceiptRequest;
use App\Models\Department;
use App\Models\Item;
use App\Services\User\ItemService;
use App\Services\User\StockLedgerService;
use App\Support\StockMovementType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ItemStockController extends Controller
{
    public function __construct(
        private StockLedgerService $stockLedgerService,
        private ItemService $itemService,
    ) {}

    public function show(ShowRequest $request, Department $department, Item $item): JsonResponse
    {
        abort_unless($item->department_id === $department->id, 404);

        return response()->json($this->itemService->stockPayload($item));
    }

    public function storeReceipt(
        StoreReceiptRequest $request,
        Department $department,
        Item $item,
    ): RedirectResponse {
        abort_unless($item->department_id === $department->id, 404);

        $this->stockLedgerService->receive($item, $request->user(), $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Stock received.');
    }

    public function storeAdjustment(
        StoreAdjustmentRequest $request,
        Department $department,
        Item $item,
    ): RedirectResponse {
        abort_unless($item->department_id === $department->id, 404);

        $this->stockLedgerService->adjust($item, $request->user(), $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Stock adjusted.');
    }

    public function storeAllocation(
        StoreAllocationRequest $request,
        Department $department,
        Item $item,
    ): RedirectResponse {
        abort_unless($item->department_id === $department->id, 404);

        $validated = $request->validated();

        if ($validated['type'] === StockMovementType::Deallocate) {
            $this->stockLedgerService->deallocate($item, $request->user(), $validated);
            $message = 'Stock deallocated.';
        } else {
            $this->stockLedgerService->allocate($item, $request->user(), $validated);
            $message = 'Stock allocated.';
        }

        return redirect()
            ->back()
            ->with('success', $message);
    }
}
