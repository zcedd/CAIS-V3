<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class BuildLatestAssistanceRequestSubStatusSubquery
{
    public function __invoke(?int $programId = null): Builder
    {
        $pivotTable = (new AssistanceRequestSubStatus)->getTable();
        $assistanceTable = (new Assistance)->getTable();

        $maxRecordedAtPerAssistance = DB::table($pivotTable)
            ->select('assistance_id')
            ->selectRaw("MAX({$pivotTable}.recorded_at) as max_recorded_at")
            ->whereNull("{$pivotTable}.deleted_at");

        if ($programId !== null) {
            $maxRecordedAtPerAssistance->whereIn("{$pivotTable}.assistance_id", function (Builder $subquery) use ($assistanceTable, $programId): void {
                $subquery
                    ->from($assistanceTable)
                    ->select('id')
                    ->where('program_id', $programId);
            });
        }

        return DB::table("{$pivotTable} as arss")
            ->select('arss.assistance_id')
            ->selectRaw('MAX(arss.id) as latest_arss_id')
            ->joinSub(
                $maxRecordedAtPerAssistance->groupBy("{$pivotTable}.assistance_id"),
                'latest_recorded_at',
                function ($join): void {
                    $join->on('latest_recorded_at.assistance_id', '=', 'arss.assistance_id')
                        ->on('latest_recorded_at.max_recorded_at', '=', 'arss.recorded_at');
                },
            )
            ->whereNull('arss.deleted_at')
            ->groupBy('arss.assistance_id');
    }
}
