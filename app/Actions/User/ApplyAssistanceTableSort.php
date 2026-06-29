<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\Beneficiary;
use App\Models\ModeOfRequest;
use Illuminate\Database\Eloquent\Builder;

class ApplyAssistanceTableSort
{
    /**
     * @param  Builder<Assistance>  $query
     */
    public function __invoke(Builder $query, string $sort, string $direction): void
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'cais_number' => $query->orderBy(
                Beneficiary::query()
                    ->select('cais_number')
                    ->whereColumn('beneficiaries.id', 'assistances.beneficiary_id')
                    ->limit(1),
                $direction,
            ),
            'mode_of_request' => $query->orderBy(
                ModeOfRequest::query()
                    ->select('name')
                    ->whereColumn('mode_of_requests.id', 'assistances.mode_of_request_id')
                    ->limit(1),
                $direction,
            ),
            'status' => $query->orderByRaw(
                'CASE
                    WHEN date_denied IS NOT NULL THEN 1
                    WHEN date_delivered IS NOT NULL THEN 2
                    WHEN date_verified IS NOT NULL THEN 3
                    WHEN date_requested IS NOT NULL THEN 4
                    ELSE 5
                END '.$direction,
            ),
            'date_requested',
            'date_verified',
            'date_delivered',
            'date_denied',
            'remark' => $query->orderBy($sort, $direction),
            default => $query->orderBy('assistances.id', $direction),
        };
    }
}
