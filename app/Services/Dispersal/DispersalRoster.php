<?php

namespace App\Services\Dispersal;

use App\Actions\User\EvaluateAssistanceEligibility;
use App\Enums\DispersalBatchStatus;
use App\Enums\DispersalItemApprovalStatus;
use App\Enums\ItemKind;
use App\Enums\RequestSubStatusCode;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\DispersalBatch;
use App\Models\DispersalBatchItem;
use App\Models\ProgramItemStock;
use App\Models\User;
use App\Support\DecimalAmount;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DispersalRoster
{
    public function __construct(
        private EvaluateAssistanceEligibility $evaluateAssistanceEligibility,
        private DispersalAudit $audit,
        private DispersalApprovalPolicyResolver $policies,
    ) {}

    /**
     * @param  list<int>  $assistanceIds
     */
    public function sync(DispersalBatch $batch, array $assistanceIds, User $actor): void
    {
        if (! $batch->isEditable()) {
            throw ValidationException::withMessages([
                'assistance_ids' => 'This dispersal batch cannot be edited.',
            ]);
        }

        $batch->loadMissing('program');
        $assistanceIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $assistanceIds)));
        $policy = $this->policies->resolve($batch->program, $batch->approval_mode, '0.00');
        $existing = $batch->items()->pluck('assistance_id')->map(static fn (mixed $id): int => (int) $id)->all();
        $removed = array_values(array_diff($existing, $assistanceIds));

        if ($removed !== []) {
            $batch->items()->whereIn('assistance_id', $removed)->delete();
            $this->audit->record($actor, $batch, 'roster_removed', [
                'revision' => $batch->revision,
                'assistance_ids' => $removed,
            ]);
        }

        foreach ($assistanceIds as $assistanceId) {
            $current = $batch->items()->where('assistance_id', $assistanceId)->first();

            if ($current instanceof DispersalBatchItem) {
                $this->refreshLines($current);

                continue;
            }

            $this->add($batch, $assistanceId, (bool) $policy->block_active_batch_reuse, $actor);
        }
    }

    /**
     * @return array{
     *     errors: list<string>,
     *     warnings: list<string>,
     *     stock: list<array{item_id: int, name: string, requested: string, available: string}>
     * }
     */
    public function inspect(DispersalBatch $batch): array
    {
        $batch->load(['items.lines.item', 'items.assistance.beneficiary', 'items.assistance.currentRequestSubStatus', 'program']);

        $errors = [];
        $warnings = [];

        if ($batch->items->isEmpty()) {
            $errors[] = 'Add at least one assistance request before continuing.';
        }

        if (! $batch->program?->isEncodable()) {
            $errors[] = 'The program is not open for dispersal.';
        }

        foreach ($batch->items as $item) {
            $itemErrors = [];
            $itemWarnings = [];

            if ($item->lines->isEmpty()) {
                $itemErrors[] = 'This request has no assistance left to release.';
            }

            if ($item->assistance instanceof Assistance && $item->assistance->was_delivered) {
                $itemErrors[] = 'This request is already delivered.';
            }

            if ($item->assistance instanceof Assistance && ! $this->hasEligibleStatus($item->assistance)) {
                $itemErrors[] = 'This request is not verified, approved, or ready for release.';
            }

            if ($item->assistance?->beneficiary !== null && $item->assistance instanceof Assistance) {
                $findings = ($this->evaluateAssistanceEligibility)(
                    $batch->program,
                    $item->assistance->beneficiary,
                    $this->itemDetails($item),
                    null,
                    $item->assistance_id,
                );

                foreach ($findings as $finding) {
                    if (($finding['severity'] ?? null) === 'hard') {
                        $itemErrors[] = (string) $finding['message'];
                    } else {
                        $itemWarnings[] = (string) $finding['message'];
                    }
                }
            }

            $item->forceFill([
                'warnings' => $itemWarnings,
            ])->save();

            foreach ($itemErrors as $message) {
                $errors[] = $message;
            }

            foreach ($itemWarnings as $message) {
                $warnings[] = $message;
            }
        }

        $stock = $this->stockWarnings($batch);

        foreach ($stock as $row) {
            if (DecimalAmount::gt($row['requested'], $row['available'])) {
                $warnings[] = sprintf(
                    '%s exceeds the program allocation. Requested %s, available %s.',
                    $row['name'],
                    $row['requested'],
                    $row['available'],
                );
            }
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'stock' => $stock,
        ];
    }

    private function add(DispersalBatch $batch, int $assistanceId, bool $blockReuse, User $actor): void
    {
        $assistance = Assistance::query()
            ->with(['beneficiary', 'assistanceItem.item', 'currentRequestSubStatus'])
            ->find($assistanceId);

        if (! $assistance instanceof Assistance || (int) $assistance->program_id !== (int) $batch->program_id) {
            throw ValidationException::withMessages([
                'assistance_ids' => 'Each assistance request must belong to the selected program.',
            ]);
        }

        if ($assistance->beneficiary_id === null) {
            throw ValidationException::withMessages([
                'assistance_ids' => 'Each assistance request needs a beneficiary.',
            ]);
        }

        $this->assertEligible($assistance);

        if ($blockReuse && $this->reservedElsewhere($batch, $assistance->id)) {
            throw ValidationException::withMessages([
                'assistance_ids' => 'An assistance request is already assigned to another active dispersal batch.',
            ]);
        }

        $item = $batch->items()->create([
            'assistance_id' => $assistance->id,
            'beneficiary_id' => $assistance->beneficiary_id,
            'approval_status' => DispersalItemApprovalStatus::Pending,
        ]);

        $this->writeLines($item, $assistance);

        $this->audit->record($actor, $batch, 'roster_added', [
            'revision' => $batch->revision,
            'assistance_id' => $assistance->id,
            'beneficiary_id' => $assistance->beneficiary_id,
        ]);
    }

    private function refreshLines(DispersalBatchItem $item): void
    {
        if (! in_array($item->approval_status, [
            DispersalItemApprovalStatus::Pending,
            DispersalItemApprovalStatus::Returned,
        ], true)) {
            return;
        }

        $assistance = Assistance::query()
            ->with(['assistanceItem.item', 'currentRequestSubStatus'])
            ->findOrFail($item->assistance_id);

        $this->assertEligible($assistance);
        $item->lines()->delete();
        $this->writeLines($item, $assistance);
    }

    private function assertEligible(Assistance $assistance): void
    {
        if ($assistance->was_delivered) {
            throw ValidationException::withMessages([
                'assistance_ids' => 'Delivered assistance requests cannot be added to a dispersal batch.',
            ]);
        }

        if (! $this->hasEligibleStatus($assistance)) {
            throw ValidationException::withMessages([
                'assistance_ids' => 'Only verified, approved, or ready-for-release requests can be added.',
            ]);
        }
    }

    private function hasEligibleStatus(Assistance $assistance): bool
    {
        $assistance->loadMissing('currentRequestSubStatus');

        foreach ([
            RequestSubStatusCode::Verified,
            RequestSubStatusCode::Approved,
            RequestSubStatusCode::ReadyForRelease,
        ] as $code) {
            if ($code->matches($assistance->currentRequestSubStatus)) {
                return true;
            }
        }

        return false;
    }

    private function reservedElsewhere(DispersalBatch $batch, int $assistanceId): bool
    {
        return DispersalBatchItem::query()
            ->where('assistance_id', $assistanceId)
            ->where('dispersal_batch_id', '!=', $batch->id)
            ->whereHas('batch', function ($query): void {
                $query->whereIn('status', DispersalBatchStatus::blockingValues());
            })
            ->exists();
    }

    private function writeLines(DispersalBatchItem $item, Assistance $assistance): void
    {
        $rows = $this->openLines($assistance);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'assistance_ids' => 'An assistance request has nothing left to release.',
            ]);
        }

        foreach ($rows as $row) {
            $kind = $row->item?->kind;
            $quantity = $this->lineQuantity($row);

            $item->lines()->create([
                'assistance_item_id' => $row->id,
                'item_id' => $row->item_id,
                'proposed_quantity' => $kind instanceof ItemKind && $kind->isCash() ? null : $quantity,
                'proposed_amount' => $kind instanceof ItemKind && $kind->isCash() ? $quantity : null,
            ]);
        }
    }

    private function lineQuantity(AssistanceItem $row): string
    {
        $requested = DecimalAmount::of((string) ($row->requested_quantity ?? 0));

        if (DecimalAmount::isPositive($requested)) {
            return $requested;
        }

        return DecimalAmount::of((string) ($row->quantity ?? 0));
    }

    /**
     * @return Collection<int, AssistanceItem>
     */
    private function openLines(Assistance $assistance): Collection
    {
        return $assistance->assistanceItem
            ->filter(function (AssistanceItem $row): bool {
                if ($row->is_received || $row->substituted_at !== null) {
                    return false;
                }

                return DecimalAmount::isPositive($this->lineQuantity($row));
            })
            ->values();
    }

    /**
     * @return list<array{item_id: int, quantity: int}>
     */
    private function itemDetails(DispersalBatchItem $item): array
    {
        return $item->lines
            ->map(static fn ($line): array => [
                'item_id' => (int) $line->item_id,
                'quantity' => DecimalAmount::toInt($line->proposed_quantity ?? $line->proposed_amount ?? '0'),
            ])
            ->all();
    }

    /**
     * @return list<array{item_id: int, name: string, requested: string, available: string}>
     */
    private function stockWarnings(DispersalBatch $batch): array
    {
        $requested = [];

        foreach ($batch->items as $item) {
            if ($item->approval_status === DispersalItemApprovalStatus::Excluded) {
                continue;
            }

            foreach ($item->lines as $line) {
                if ($line->proposed_quantity === null) {
                    continue;
                }

                $itemId = (int) $line->item_id;
                $requested[$itemId]['name'] = $line->item?->name ?? 'Item';
                $requested[$itemId]['requested'] = DecimalAmount::add(
                    $requested[$itemId]['requested'] ?? '0.00',
                    $line->proposed_quantity,
                );
            }
        }

        $rows = [];

        foreach ($requested as $itemId => $row) {
            $available = ProgramItemStock::query()
                ->where('program_id', $batch->program_id)
                ->where('item_id', $itemId)
                ->value('remaining');

            $rows[] = [
                'item_id' => (int) $itemId,
                'name' => $row['name'],
                'requested' => $row['requested'],
                'available' => DecimalAmount::of($available === null ? '0' : (string) $available),
            ];
        }

        return $rows;
    }
}
