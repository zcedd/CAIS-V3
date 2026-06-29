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
                    WHEN {$assistanceTable}.date_denied IS NOT NULL THEN 1
                    WHEN {$assistanceTable}.date_delivered IS NOT NULL THEN 2
                    WHEN {$assistanceTable}.date_verified IS NOT NULL THEN 3
                    WHEN {$assistanceTable}.date_requested IS NOT NULL THEN 4
                    ELSE 5
                END {$direction}",
            ),
            'request_sub_status_recorded_at' => $query->orderBy('arss.recorded_at', $direction),
            'date_requested',
            'date_verified',
            'date_delivered',
            'date_denied',
            'remark' => $query->orderBy("{$assistanceTable}.{$sort}", $direction),
            default => $query->orderBy("{$assistanceTable}.id", $direction),
        };
    }
}
