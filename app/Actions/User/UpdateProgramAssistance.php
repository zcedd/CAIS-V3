<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Beneficiary;
use App\Services\User\ProgramFieldService;
use App\Support\AssistanceItemOrigin;
use Illuminate\Support\Facades\DB;

class UpdateProgramAssistance
{
    public function __construct(
        private ProgramFieldService $programFieldService,
        private GuardAssistanceEligibility $guardAssistanceEligibility,
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
     *     }>,
     *     eligibility_override_reason?: string|null
     * }  $validated
     */
    public function __invoke(Assistance $assistance, array $validated): Assistance
    {
        return DB::transaction(function () use ($assistance, $validated): Assistance {
            $beneficiary = Beneficiary::query()
                ->lockForUpdate()
                ->findOrFail($validated['beneficiary_id']);

            $overrideReason = isset($validated['eligibility_override_reason'])
                ? trim((string) $validated['eligibility_override_reason'])
                : null;
            $overrideReason = $overrideReason === '' ? null : $overrideReason;

            $this->guardAssistanceEligibility->assert(
                $assistance->program,
                $beneficiary,
                collect($validated['item_details'])
                    ->map(static fn (array $row): array => [
                        'item_id' => (int) $row['item_id'],
                        'quantity' => (int) $row['quantity'],
                    ])
                    ->all(),
                $overrideReason,
                now(),
                $assistance->id,
            );

            $assistance->update([
                'beneficiary_id' => $beneficiary->id,
                'mode_of_request_id' => $validated['mode_of_request_id'],
                'remark' => $validated['remark'] ?? null,
                'eligibility_override_reason' => $overrideReason,
            ]);

            // Released and substituted lines record what actually happened, so only the
            // outstanding part of the request is rewritten here.
            AssistanceItem::query()
                ->where('assistance_id', $assistance->id)
                ->awaitingRelease()
                ->delete();

            foreach ($validated['item_details'] as $itemDetail) {
                AssistanceItem::query()->create([
                    'assistance_id' => $assistance->id,
                    'item_id' => $itemDetail['item_id'],
                    'origin' => AssistanceItemOrigin::Requested,
                    'quantity' => $itemDetail['quantity'],
                    'requested_quantity' => $itemDetail['quantity'],
                    'specification' => $itemDetail['specification'] ?? null,
                    'is_received' => false,
                ]);
            }

            $this->programFieldService->syncValuesForAssistance(
                $assistance,
                $validated['field_values'] ?? [],
            );

            return $assistance->refresh();
        });
    }
}
