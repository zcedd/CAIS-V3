<?php

namespace App\Actions\User;

use App\Models\Assistance;
use Illuminate\Database\Eloquent\Builder;

class JoinAssistanceStatusRelations
{
    /**
     * Join the denormalized current status lookup tables onto an assistance query.
     *
     * @param  Builder<Assistance>  $query
     */
    public function __invoke(Builder $query): void
    {
        $assistanceTable = (new Assistance)->getTable();

        $query
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
