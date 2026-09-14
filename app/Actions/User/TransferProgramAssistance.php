<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Beneficiary;
use App\Models\Program;
use App\Models\RequestSubStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransferProgramAssistance
{
    public function __construct(
        private GuardAssistanceEligibility $guardAssistanceEligibility,
    ) {}

    /**
     * @param  array{target_program_id: int, reason: string, eligibility_override_reason?: string|null}  $validated
     */
    public function __invoke(Assistance $assistance, Program $targetProgram, array $validated): Assistance
    {
        return DB::transaction(function () use ($assistance, $targetProgram, $validated): Assistance {
            $beneficiary = Beneficiary::query()
                ->lockForUpdate()
                ->findOrFail($assistance->beneficiary_id);

            $assistance->loadMissing(['assistanceItem', 'program:id,name']);

            $overrideReason = isset($validated['eligibility_override_reason'])
                ? trim((string) $validated['eligibility_override_reason'])
                : null;
            $overrideReason = $overrideReason === '' ? null : $overrideReason;
            $reason = trim((string) ($validated['reason'] ?? ''));
            $sourceName = $assistance->program?->name ?? 'the current program';

            $this->guardAssistanceEligibility->assert(
                $targetProgram,
                $beneficiary,
                $assistance->assistanceItem
                    ->map(static fn ($item): array => [
                        'item_id' => (int) $item->item_id,
                        'quantity' => (int) $item->quantity,
                    ])
                    ->all(),
                $overrideReason,
                now(),
                $assistance->id,
            );

            $assistance->update([
                'program_id' => $targetProgram->id,
                'eligibility_override_reason' => $overrideReason ?? $assistance->eligibility_override_reason,
            ]);

            $currentSubStatusId = $assistance->current_request_sub_status_id
                ?? RequestSubStatus::query()->where('name', 'In Progress')->value('id');

            if ($currentSubStatusId !== null && $reason !== '') {
                AssistanceRequestSubStatus::query()->create([
                    'assistance_id' => $assistance->id,
                    'request_sub_status_id' => $currentSubStatusId,
                    'remark' => "Transferred from {$sourceName} to {$targetProgram->name}: {$reason}",
                    'recorded_at' => Carbon::now(),
                ]);
            }

            if ($overrideReason !== null && $currentSubStatusId !== null) {
                AssistanceRequestSubStatus::query()->create([
                    'assistance_id' => $assistance->id,
                    'request_sub_status_id' => $currentSubStatusId,
                    'remark' => 'Eligibility override on transfer: '.$overrideReason,
                    'recorded_at' => Carbon::now(),
                ]);
            }

            return $assistance->refresh();
        });
    }
}
