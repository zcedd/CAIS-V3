<?php

namespace App\Actions\User;

use App\Models\Assistance;
use Illuminate\Database\Eloquent\Builder;

class JoinAssistanceTableRelations
{
    /**
     * @param  Builder<Assistance>  $query
     */
    public function __invoke(Builder $query): void
    {
        $assistanceTable = (new Assistance)->getTable();

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
            ->leftJoin(
                'request_sub_statuses as rss',
                'rss.id',
                '=',
                "{$assistanceTable}.current_request_sub_status_id",
            )
            ->leftJoin(
                'request_statuses as rs',
                'rs.id',
                '=',
                'rss.request_status_id',
            );
    }
}
