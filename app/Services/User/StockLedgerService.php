<?php

namespace App\Services\User;

use App\Enums\ItemKind;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Item;
use App\Models\ItemStockBalance;
use App\Models\Program;
use App\Models\ProgramItemStock;
use App\Models\StockLot;
use App\Models\StockLotBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockLedgerService
{
    public function __construct(
        private LowStockNotificationService $lowStockNotificationService,
    ) {}

    /**
     * @param  array{
     *     quantity: int,
     *     type: string,
     *     batch_number?: string|null,
     *     expires_at?: string|null,
     *     received_at?: string|null,
     *     reason?: string|null
     * }  $payload
     */
    public function receive(Item $item, User $user, array $payload): StockLot
    {
        $quantity = (int) $payload['quantity'];
        $type = $payload['type'] instanceof StockMovementType
            ? $payload['type']
            : StockMovementType::from((string) $payload['type']);

        if ($quantity < 1) {
            throw new InsufficientStockException('Quantity must be at least 1.');
        }

        $this->assertTracksInventory($item);

        if (! in_array($type, StockMovementType::receipts(), true)) {
            throw ValidationException::withMessages([
                'type' => ['Receipt type is invalid.'],
            ]);
        }

        if ($item->is_perishable && (blank($payload['batch_number'] ?? null) || blank($payload['expires_at'] ?? null))) {
            throw ValidationException::withMessages([
                'batch_number' => ['Batch number and expiry date are required for perishable items.'],
                'expires_at' => ['Batch number and expiry date are required for perishable items.'],
            ]);
        }

        return DB::transaction(function () use ($item, $user, $payload, $quantity, $type): StockLot {
            $lot = StockLot::query()->create([
                'department_id' => $item->department_id,
                'item_id' => $item->id,
                'batch_number' => $payload['batch_number'] ?? null,
                'expires_at' => $payload['expires_at'] ?? null,
                'received_at' => isset($payload['received_at'])
                    ? Carbon::parse($payload['received_at'])
                    : now(),
            ]);

            $this->lockItemBalance($item);
            $lotBalance = $this->lockLotBalance($lot);

            $this->recordMovement([
                'department_id' => $item->department_id,
                'item_id' => $item->id,
                'stock_lot_id' => $lot->id,
                'type' => $type,
                'quantity' => $quantity,
                'user_id' => $user->id,
                'reason' => $payload['reason'] ?? null,
            ]);

            $lotBalance->increment('on_hand', $quantity);
            $this->adjustItemBalance($item, onHandDelta: $quantity, allocatedDelta: 0);

            DB::afterCommit(fn () => $this->lowStockNotificationService->notifyIfNeeded($item->fresh()));

            return $lot->fresh() ?? $lot;
        });
    }

    /**
     * @param  array{
     *     stock_lot_id: int,
     *     type: string,
     *     quantity: int,
     *     reason: string
     * }  $payload
     */
    public function adjust(Item $item, User $user, array $payload): void
    {
        $quantity = (int) $payload['quantity'];
        $type = $payload['type'] instanceof StockMovementType
            ? $payload['type']
            : StockMovementType::from((string) $payload['type']);

        if ($quantity < 1) {
            throw new InsufficientStockException('Quantity must be at least 1.');
        }

        $this->assertTracksInventory($item);

        if (! in_array($type, [StockMovementType::AdjustmentIn, StockMovementType::AdjustmentOut], true)) {
            throw ValidationException::withMessages([
                'type' => ['Adjustment type is invalid.'],
            ]);
        }

        DB::transaction(function () use ($item, $user, $payload, $quantity, $type): void {
            $lot = StockLot::query()
                ->where('item_id', $item->id)
                ->where('department_id', $item->department_id)
                ->whereKey($payload['stock_lot_id'])
                ->firstOrFail();

            $itemBalance = $this->lockItemBalance($item);
            $lotBalance = $this->lockLotBalance($lot);

            if ($type === StockMovementType::AdjustmentOut) {
                $maxOut = min($lotBalance->on_hand, $itemBalance->available);

                if ($quantity > $maxOut) {
                    throw new InsufficientStockException(
                        "Cannot write off {$quantity} of {$item->name}. Unallocated available stock on this lot is {$maxOut}. Deallocate from programs first if needed.",
                    );
                }

                $lotBalance->decrement('on_hand', $quantity);
                $this->adjustItemBalance($item, onHandDelta: -$quantity, allocatedDelta: 0);
            } else {
                $lotBalance->increment('on_hand', $quantity);
                $this->adjustItemBalance($item, onHandDelta: $quantity, allocatedDelta: 0);
            }

            $this->recordMovement([
                'department_id' => $item->department_id,
                'item_id' => $item->id,
                'stock_lot_id' => $lot->id,
                'type' => $type,
                'quantity' => $quantity,
                'user_id' => $user->id,
                'reason' => $payload['reason'],
            ]);

            DB::afterCommit(fn () => $this->lowStockNotificationService->notifyIfNeeded($item->fresh()));
        });
    }

    /**
     * @param  array{program_id: int, quantity: int, reason?: string|null}  $payload
     */
    public function allocate(Item $item, User $user, array $payload): void
    {
        $this->shiftAllocation($item, $user, $payload, StockMovementType::Allocate);
    }

    /**
     * @param  array{program_id: int, quantity: int, reason?: string|null}  $payload
     */
    public function deallocate(Item $item, User $user, array $payload): void
    {
        $this->shiftAllocation($item, $user, $payload, StockMovementType::Deallocate);
    }

    public function issue(AssistanceItem $assistanceItem, Program $program, User $user): void
    {
        $quantity = (int) $assistanceItem->quantity;

        if ($quantity < 1 || ! $assistanceItem->is_received) {
            return;
        }

        $item = Item::query()->findOrFail($assistanceItem->item_id);

        if (! $item->tracksInventory()) {
            return;
        }

        DB::transaction(function () use ($assistanceItem, $program, $user, $item, $quantity): void {
            $assistanceItem = AssistanceItem::query()
                ->whereKey($assistanceItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyIssued = StockMovement::query()
                ->where('assistance_item_id', $assistanceItem->id)
                ->where('type', StockMovementType::Issue)
                ->lockForUpdate()
                ->exists();

            if ($alreadyIssued) {
                return;
            }

            $itemBalance = $this->lockItemBalance($item);
            $programStock = $this->lockProgramStock($program, $item);

            if ($quantity > $programStock->remaining) {
                throw new InsufficientStockException(
                    "Not enough allocated stock of {$item->name} for this program. Remaining allocation is {$programStock->remaining}.",
                    'delivered_items',
                );
            }

            $remainingToIssue = $quantity;
            $picks = $this->pickLotsFefo($item, $remainingToIssue);

            $issuable = array_sum(array_column($picks, 'quantity'));

            if ($issuable < $remainingToIssue) {
                throw new InsufficientStockException(
                    "Not enough issuable stock of {$item->name}. Allocate stock and ensure lots are not expired.",
                    'delivered_items',
                );
            }

            foreach ($picks as $pick) {
                /** @var StockLotBalance $lotBalance */
                $lotBalance = $pick['balance'];
                $take = (int) $pick['quantity'];

                $lotBalance->decrement('on_hand', $take);

                $this->recordMovement([
                    'department_id' => $item->department_id,
                    'item_id' => $item->id,
                    'program_id' => $program->id,
                    'stock_lot_id' => $lotBalance->stock_lot_id,
                    'type' => StockMovementType::Issue,
                    'quantity' => $take,
                    'assistance_item_id' => $assistanceItem->id,
                    'user_id' => $user->id,
                    'reason' => 'Released on delivery',
                ]);
            }

            $programStock->decrement('remaining', $quantity);
            $this->adjustItemBalance($item, onHandDelta: -$quantity, allocatedDelta: -$quantity);

            unset($itemBalance);

            DB::afterCommit(fn () => $this->lowStockNotificationService->notifyIfNeeded($item->fresh()));
        });
    }

    public function restoreForAssistance(Assistance $assistance, User $user): void
    {
        $itemIds = AssistanceItem::query()
            ->where('assistance_id', $assistance->id)
            ->where('is_received', true)
            ->pluck('id');

        if ($itemIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($itemIds, $user): void {
            $issues = StockMovement::query()
                ->whereIn('assistance_item_id', $itemIds)
                ->where('type', StockMovementType::Issue)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            foreach ($issues as $issue) {
                $this->restoreIssue($issue, $user);
            }
        });
    }

    public function remainingForProgramItem(Program $program, int $itemId): int
    {
        return (int) ProgramItemStock::query()
            ->where('program_id', $program->id)
            ->where('item_id', $itemId)
            ->value('remaining');
    }

    /**
     * @return array<int, int>
     */
    public function remainingByItemId(Program $program): array
    {
        return ProgramItemStock::query()
            ->where('program_id', $program->id)
            ->pluck('remaining', 'item_id')
            ->map(static fn ($remaining): int => (int) $remaining)
            ->all();
    }

    /**
     * @return list<array{
     *     id: int,
     *     program_id: int,
     *     program_name: string,
     *     item_id: int,
     *     item_name: string,
     *     unit: string|null,
     *     remaining: int,
     *     on_hand: int,
     *     threshold: int|null,
     *     is_low: bool
     * }>
     */
    public function programStockTable(Program $program): array
    {
        $itemIds = $program->item()->pluck('items.id');

        if ($itemIds->isEmpty()) {
            return [];
        }

        $remaining = ProgramItemStock::query()
            ->where('program_id', $program->id)
            ->whereIn('item_id', $itemIds)
            ->pluck('remaining', 'item_id');

        return Item::query()
            ->whereIn('id', $itemIds)
            ->where('kind', ItemKind::Goods)
            ->with([
                'unitMeasurement:id,name',
                'stockBalance' => fn ($query) => $query->where('department_id', $program->department_id),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'kind', 'item_unit_measurement_id', 'low_stock_threshold', 'department_id'])
            ->map(function (Item $item) use ($program, $remaining): array {
                $remainingQty = (int) ($remaining[$item->id] ?? 0);
                $onHand = (int) ($item->stockBalance?->on_hand ?? 0);
                $threshold = $item->low_stock_threshold;

                return [
                    'id' => $item->id,
                    'program_id' => $program->id,
                    'program_name' => $program->name,
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'unit' => $item->unitMeasurement?->name,
                    'remaining' => $remainingQty,
                    'on_hand' => $onHand,
                    'threshold' => $threshold,
                    'is_low' => $threshold !== null && $remainingQty <= $threshold,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{program_id: int, quantity: int, reason?: string|null}  $payload
     */
    private function shiftAllocation(Item $item, User $user, array $payload, StockMovementType $type): void
    {
        $quantity = (int) $payload['quantity'];

        if ($quantity < 1) {
            throw new InsufficientStockException('Quantity must be at least 1.');
        }

        $this->assertTracksInventory($item);

        $program = Program::query()->findOrFail($payload['program_id']);

        if ($program->department_id !== $item->department_id) {
            throw ValidationException::withMessages([
                'program_id' => ['The program does not belong to this department.'],
            ]);
        }

        $linked = $program->item()->where('items.id', $item->id)->exists();

        if (! $linked) {
            throw ValidationException::withMessages([
                'program_id' => ['Allocate stock only to programs that include this item.'],
            ]);
        }

        DB::transaction(function () use ($item, $user, $program, $payload, $quantity, $type): void {
            $itemBalance = $this->lockItemBalance($item);
            $programStock = $this->lockProgramStock($program, $item);

            if ($type === StockMovementType::Allocate) {
                if ($quantity > $itemBalance->available) {
                    throw new InsufficientStockException(
                        "Cannot allocate {$quantity} of {$item->name}. Unallocated available stock is {$itemBalance->available}.",
                    );
                }

                $this->adjustItemBalance($item, onHandDelta: 0, allocatedDelta: $quantity);
                $programStock->increment('remaining', $quantity);
            } else {
                if ($quantity > $programStock->remaining) {
                    throw new InsufficientStockException(
                        "Cannot deallocate {$quantity} of {$item->name}. Remaining program allocation is {$programStock->remaining}.",
                    );
                }

                $this->adjustItemBalance($item, onHandDelta: 0, allocatedDelta: -$quantity);
                $programStock->decrement('remaining', $quantity);
            }

            $this->recordMovement([
                'department_id' => $item->department_id,
                'item_id' => $item->id,
                'program_id' => $program->id,
                'type' => $type,
                'quantity' => $quantity,
                'user_id' => $user->id,
                'reason' => $payload['reason'] ?? null,
            ]);
        });
    }

    private function restoreIssue(StockMovement $issue, User $user): void
    {
        $alreadyRestored = StockMovement::query()
            ->where('reverses_movement_id', $issue->id)
            ->where('type', StockMovementType::Restore)
            ->lockForUpdate()
            ->exists();

        if ($alreadyRestored) {
            return;
        }

        $item = Item::query()->findOrFail($issue->item_id);

        if (! $item->tracksInventory()) {
            return;
        }

        $this->lockItemBalance($item);

        if ($issue->stock_lot_id !== null) {
            $lot = StockLot::query()->findOrFail($issue->stock_lot_id);
            $lotBalance = $this->lockLotBalance($lot);
            $lotBalance->increment('on_hand', $issue->quantity);
        }

        if ($issue->program_id !== null) {
            $program = Program::query()->findOrFail($issue->program_id);
            $programStock = $this->lockProgramStock($program, $item);
            $programStock->increment('remaining', $issue->quantity);
        }

        $this->adjustItemBalance($item, onHandDelta: $issue->quantity, allocatedDelta: $issue->quantity);

        $this->recordMovement([
            'department_id' => $issue->department_id,
            'item_id' => $issue->item_id,
            'program_id' => $issue->program_id,
            'stock_lot_id' => $issue->stock_lot_id,
            'type' => StockMovementType::Restore,
            'quantity' => $issue->quantity,
            'assistance_item_id' => $issue->assistance_item_id,
            'reverses_movement_id' => $issue->id,
            'user_id' => $user->id,
            'reason' => 'Restored after deny or delete',
        ]);

        DB::afterCommit(fn () => $this->lowStockNotificationService->notifyIfNeeded($item->fresh()));
    }

    /**
     * @return list<array{balance: StockLotBalance, quantity: int}>
     */
    private function pickLotsFefo(Item $item, int $quantity): array
    {
        $today = now()->toDateString();

        $lotBalances = StockLotBalance::query()
            ->select('stock_lot_balances.*')
            ->join('stock_lots', 'stock_lots.id', '=', 'stock_lot_balances.stock_lot_id')
            ->where('stock_lots.item_id', $item->id)
            ->where('stock_lots.department_id', $item->department_id)
            ->where('stock_lot_balances.on_hand', '>', 0)
            ->where(function ($query) use ($today): void {
                $query->whereNull('stock_lots.expires_at')
                    ->orWhereDate('stock_lots.expires_at', '>=', $today);
            })
            ->orderByRaw('stock_lots.expires_at is null')
            ->orderBy('stock_lots.expires_at')
            ->orderBy('stock_lots.id')
            ->lockForUpdate()
            ->get();

        $remaining = $quantity;
        $picks = [];

        foreach ($lotBalances as $lotBalance) {
            if ($remaining < 1) {
                break;
            }

            $take = min($remaining, (int) $lotBalance->on_hand);

            if ($take < 1) {
                continue;
            }

            $picks[] = [
                'balance' => $lotBalance,
                'quantity' => $take,
            ];
            $remaining -= $take;
        }

        return $picks;
    }

    private function assertTracksInventory(Item $item): void
    {
        if ($item->tracksInventory()) {
            return;
        }

        throw ValidationException::withMessages([
            'item' => ['This catalog entry is not stocked.'],
        ]);
    }

    private function lockItemBalance(Item $item): ItemStockBalance
    {
        ItemStockBalance::query()->firstOrCreate(
            [
                'department_id' => $item->department_id,
                'item_id' => $item->id,
            ],
            [
                'on_hand' => 0,
                'allocated' => 0,
                'available' => 0,
                'low_stock_notified' => false,
            ],
        );

        return ItemStockBalance::query()
            ->where('department_id', $item->department_id)
            ->where('item_id', $item->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockLotBalance(StockLot $lot): StockLotBalance
    {
        StockLotBalance::query()->firstOrCreate(
            ['stock_lot_id' => $lot->id],
            ['on_hand' => 0],
        );

        return StockLotBalance::query()
            ->where('stock_lot_id', $lot->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockProgramStock(Program $program, Item $item): ProgramItemStock
    {
        ProgramItemStock::query()->firstOrCreate(
            [
                'program_id' => $program->id,
                'item_id' => $item->id,
            ],
            ['remaining' => 0],
        );

        return ProgramItemStock::query()
            ->where('program_id', $program->id)
            ->where('item_id', $item->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function adjustItemBalance(Item $item, int $onHandDelta, int $allocatedDelta): void
    {
        $balance = ItemStockBalance::query()
            ->where('department_id', $item->department_id)
            ->where('item_id', $item->id)
            ->lockForUpdate()
            ->firstOrFail();

        $onHand = $balance->on_hand + $onHandDelta;
        $allocated = $balance->allocated + $allocatedDelta;

        if ($onHand < 0 || $allocated < 0 || $onHand < $allocated) {
            throw new InsufficientStockException(
                "Stock of {$item->name} cannot go below zero or below allocated quantity.",
            );
        }

        $balance->update([
            'on_hand' => $onHand,
            'allocated' => $allocated,
            'available' => $onHand - $allocated,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordMovement(array $attributes): StockMovement
    {
        return StockMovement::query()->create([
            ...$attributes,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
        ]);
    }
}
