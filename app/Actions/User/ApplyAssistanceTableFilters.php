<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ApplyAssistanceTableFilters
{
    /**
     * @param  Builder<Assistance>  $query
     * @param  list<string>  $statuses
     * @param  list<string>  $modes
     */
    public function __invoke(
        Builder $query,
        string $search,
        array $statuses,
        array $modes,
    ): void {
        if ($search !== '') {
            $needle = '%'.$search.'%';

            $query->where(
                fn (Builder $searchQuery) => $searchQuery
                    ->where('beneficiaries.name', 'like', $needle)
                    ->orWhere('beneficiaries.cais_number', 'like', $needle),
            );
        }

        if ($modes !== []) {
            $query->whereIn('mode_of_requests.name', $modes);
        }

        if ($statuses !== []) {
            $query->whereIn(
                'assistances.id',
                $this->latestAssistanceIdsByRequestStatusNames($statuses),
            );
        }
    }

    /**
     * @param  list<string>  $statuses
     */
    private function latestAssistanceIdsByRequestStatusNames(array $statuses): QueryBuilder
    {
        $pivotTable = (new AssistanceRequestSubStatus)->getTable();

        $latestRecordedAt = DB::table($pivotTable)
            ->select('assistance_id', DB::raw('MAX(recorded_at) as max_recorded_at'))
            ->whereNull('deleted_at')
            ->groupBy('assistance_id');

        return DB::table($pivotTable.' as arss')
            ->select('arss.assistance_id')
            ->joinSub(
                $latestRecordedAt,
                'latest_arss_lookup',
                function ($join): void {
                    $join->on('latest_arss_lookup.assistance_id', '=', 'arss.assistance_id')
                        ->on('latest_arss_lookup.max_recorded_at', '=', 'arss.recorded_at');
                },
            )
            ->join(
                'request_sub_statuses as rss',
                'rss.id',
                '=',
                'arss.request_sub_status_id',
            )
            ->join(
                'request_statuses as rs',
                'rs.id',
                '=',
                'rss.request_status_id',
            )
            ->whereNull('arss.deleted_at')
            ->whereIn('rs.name', $statuses);
    }
}
