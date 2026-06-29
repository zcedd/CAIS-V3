<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use Illuminate\Database\Eloquent\Builder;

class JoinAssistanceTableRelations
{
    public function __construct(
        private BuildLatestAssistanceRequestSubStatusSubquery $buildLatestAssistanceRequestSubStatusSubquery,
    ) {}

    /**
     * @param  Builder<Assistance>  $query
     */
    public function __invoke(Builder $query, ?int $programId = null): void
    {
        $assistanceTable = (new Assistance)->getTable();
        $pivotTable = (new AssistanceRequestSubStatus)->getTable();

        $latestRecordedAt = ($this->buildLatestAssistanceRequestSubStatusSubquery)($programId);

        $query
            ->leftJoin(
                'beneficiaries',
                'beneficiaries.id',
                '=',
                "{$assistanceTable}.beneficiary_id",
            )
            ->leftJoin(
                'mode_of_requests',
                'mode_of_requests.id',
                '=',
                "{$assistanceTable}.mode_of_request_id",
            )
            ->leftJoinSub(
                $latestRecordedAt,
                'latest_arss_lookup',
                function ($join) use ($assistanceTable): void {
                    $join->on(
                        'latest_arss_lookup.assistance_id',
                        '=',
                        "{$assistanceTable}.id",
                    );
                },
            )
            ->leftJoin(
                "{$pivotTable} as arss",
                function ($join): void {
                    $join->on('arss.assistance_id', '=', 'latest_arss_lookup.assistance_id')
                        ->on('arss.recorded_at', '=', 'latest_arss_lookup.max_recorded_at')
                        ->whereNull('arss.deleted_at');
                },
            )
            ->leftJoin(
                'request_sub_statuses as rss',
                'rss.id',
                '=',
                'arss.request_sub_status_id',
            )
            ->leftJoin(
                'request_statuses as rs',
                'rs.id',
                '=',
                'rss.request_status_id',
            );
    }
}
