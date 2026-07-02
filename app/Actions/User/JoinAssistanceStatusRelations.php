<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use Illuminate\Database\Eloquent\Builder;

class JoinAssistanceStatusRelations
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

        $latestArssLookup = ($this->buildLatestAssistanceRequestSubStatusSubquery)($programId);

        $query
            ->leftJoinSub(
                $latestArssLookup,
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
                'arss.id',
                '=',
                'latest_arss_lookup.latest_arss_id',
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
