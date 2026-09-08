<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Support\RequestStatusCode;
use Illuminate\Support\Carbon;

class SyncAssistanceCurrentStatus
{
    /**
     * Recompute denormalized status columns on the assistance row from its
     * status history. History is the source of truth for current status;
     * date_delivered is kept as an indexed mirror for delivery metrics.
     */
    public function __invoke(Assistance $assistance): void
    {
        $latest = AssistanceRequestSubStatus::query()
            ->where('assistance_id', $assistance->id)
            ->whereNull('deleted_at')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first(['request_sub_status_id', 'recorded_at']);

        $assistance->forceFill([
            'current_request_sub_status_id' => $latest?->request_sub_status_id,
            'current_status_recorded_at' => $latest?->recorded_at,
            'was_delivered' => $this->resolveWasDelivered($assistance, $latest !== null),
            'date_delivered' => $this->resolveDateDelivered($assistance) ?? $assistance->date_delivered,
        ])->save();
    }

    private function resolveDateDelivered(Assistance $assistance): ?string
    {
        $recordedAt = AssistanceRequestSubStatus::query()
            ->where('assistance_request_sub_status.assistance_id', $assistance->id)
            ->whereNull('assistance_request_sub_status.deleted_at')
            ->join(
                'request_sub_statuses',
                'request_sub_statuses.id',
                '=',
                'assistance_request_sub_status.request_sub_status_id',
            )
            ->join(
                'request_statuses',
                'request_statuses.id',
                '=',
                'request_sub_statuses.request_status_id',
            )
            ->where(function ($query): void {
                $query
                    ->where('request_statuses.code', RequestStatusCode::Delivered->value)
                    ->orWhere('request_statuses.name', 'Delivered');
            })
            ->orderBy('assistance_request_sub_status.recorded_at')
            ->orderBy('assistance_request_sub_status.id')
            ->value('assistance_request_sub_status.recorded_at');

        return $recordedAt !== null
            ? Carbon::parse($recordedAt)->toDateString()
            : null;
    }

    private function resolveWasDelivered(Assistance $assistance, bool $hasStatusHistory): bool
    {
        if ($hasStatusHistory) {
            return AssistanceRequestSubStatus::query()
                ->where('assistance_id', $assistance->id)
                ->whereNull('assistance_request_sub_status.deleted_at')
                ->join('request_sub_statuses', 'request_sub_statuses.id', '=', 'assistance_request_sub_status.request_sub_status_id')
                ->join('request_statuses', 'request_statuses.id', '=', 'request_sub_statuses.request_status_id')
                ->where(function ($query): void {
                    $query
                        ->where('request_statuses.code', RequestStatusCode::Delivered->value)
                        ->orWhere('request_statuses.name', 'Delivered');
                })
                ->exists();
        }

        return $assistance->date_delivered !== null
            && $assistance->assistanceItem()
                ->where('is_received', true)
                ->exists();
    }
}
