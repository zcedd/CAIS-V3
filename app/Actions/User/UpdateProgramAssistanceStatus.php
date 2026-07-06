<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\AssistanceRequestSubStatus;
use Illuminate\Support\Carbon;

class UpdateProgramAssistanceStatus
{
    /**
     * @param  array{
     *     request_sub_status_id: int,
     *     recorded_at: string,
     *     remark?: string|null,
     *     delivered_assistance_item_id?: int|null
     * }  $validated
     */
    public function __invoke(Assistance $assistance, array $validated): Assistance
    {
        $recordedAt = Carbon::parse($validated['recorded_at'])->startOfDay();

        AssistanceRequestSubStatus::query()->create([
            'assistance_id' => $assistance->id,
            'request_sub_status_id' => $validated['request_sub_status_id'],
            'remark' => $validated['remark'] ?? null,
            'recorded_at' => $recordedAt,
        ]);

        if (isset($validated['delivered_assistance_item_id'])) {
            AssistanceItem::query()
                ->where('assistance_id', $assistance->id)
                ->whereKey($validated['delivered_assistance_item_id'])
                ->update(['is_received' => true]);

            if ($assistance->date_delivered === null) {
                $assistance->update([
                    'date_delivered' => $recordedAt->toDateString(),
                ]);
            }
        }

        return $assistance->refresh();
    }
}
