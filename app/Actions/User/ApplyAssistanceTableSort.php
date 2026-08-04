<?php

namespace App\Actions\User;

use App\Models\Assistance;
use Illuminate\Database\Eloquent\Builder;

class ApplyAssistanceTableSort
{
    /**
     * @param  Builder<Assistance>  $query
     */
    public function __invoke(Builder $query, string $sort, string $direction): void
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $assistanceTable = (new Assistance)->getTable();

        match ($sort) {
            'cais_number' => $query->orderBy('beneficiaries.cais_number', $direction),
            'beneficiary_name' => $query->orderBy('beneficiaries.name', $direction),
            'mode_of_request' => $query->orderBy('mode_of_requests.name', $direction),
            'status' => $query->orderByRaw(
                "CASE
                    WHEN rs.name = 'Denied' THEN 1
                    WHEN rs.name = 'Delivered' THEN 2
                    WHEN rs.name = 'Verification' THEN 3
                    WHEN rs.name IS NOT NULL THEN 4
                    ELSE 5
                END {$direction}",
            ),
            'request_sub_status_recorded_at' => $query->orderBy("{$assistanceTable}.current_status_recorded_at", $direction),
            'date_requested',
            'date_delivered',
            'remark' => $query->orderBy("{$assistanceTable}.{$sort}", $direction),
            default => $query->orderBy("{$assistanceTable}.id", $direction),
        };
    }
}
