<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Program;
use App\Models\RequestSubStatus;
use App\Models\User;
use Illuminate\Support\Carbon;

class StoreProgramAssistance
{
    /**
     * @param  array{
     *     beneficiary_id: int,
     *     mode_of_request_id: int,
     *     recorded_at: string,
     *     remark?: string|null,
     *     item_details: list<array{
     *         item_id: int,
     *         quantity: int,
     *         specification?: string|null
     *     }>
     * }  $validated
     */
    public function __invoke(Program $program, User $user, array $validated): Assistance
    {
        $recordedAt = Carbon::parse($validated['recorded_at'])->startOfDay();

        $assistance = Assistance::query()->create([
            'program_id' => $program->id,
            'beneficiary_id' => $validated['beneficiary_id'],
            'mode_of_request_id' => $validated['mode_of_request_id'],
            'date_requested' => $recordedAt->toDateString(),
            'remark' => $validated['remark'] ?? null,
            'user_id' => $user->id,
        ]);

        $inProgressSubStatusId = RequestSubStatus::query()
            ->where('name', 'In Progress')
            ->value('id');

        if ($inProgressSubStatusId !== null) {
            AssistanceRequestSubStatus::query()->create([
                'assistance_id' => $assistance->id,
                'request_sub_status_id' => $inProgressSubStatusId,
                'remark' => null,
                'recorded_at' => $recordedAt,
            ]);
        }

        foreach ($validated['item_details'] as $itemDetail) {
            AssistanceItem::query()->create([
                'assistance_id' => $assistance->id,
                'item_id' => $itemDetail['item_id'],
                'quantity' => $itemDetail['quantity'],
                'specification' => $itemDetail['specification'] ?? null,
                'is_received' => false,
            ]);
        }

        return $assistance;
    }
}
