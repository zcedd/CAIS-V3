<?php

namespace App\Actions\User;

use App\Enums\RequestStatusCode;
use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class SyncAssistanceCurrentStatus
{
    private ?bool $requestStatusesHaveCodeColumn = null;

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
            ->where(function (Builder $query): void {
                $this->constrainDeliveredStatus($query);
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
                ->where(function (Builder $query): void {
                    $this->constrainDeliveredStatus($query);
                })
                ->exists();
        }

        return $assistance->date_delivered !== null
            && $assistance->assistanceItem()
                ->where('is_received', true)
                ->exists();
    }

    /**
     * Match Delivered by name when request_statuses.code is not on the
     * schema yet (this action backfills before that column is added).
     *
     * @param  Builder<AssistanceRequestSubStatus>  $query
     */
    private function constrainDeliveredStatus(Builder $query): void
    {
        $query->where('request_statuses.name', RequestStatusCode::Delivered->label());

        if ($this->requestStatusesHaveCodeColumn()) {
            $query->orWhere('request_statuses.code', RequestStatusCode::Delivered->value);
        }
    }

    private function requestStatusesHaveCodeColumn(): bool
    {
        return $this->requestStatusesHaveCodeColumn ??= Schema::hasColumn('request_statuses', 'code');
    }
}
