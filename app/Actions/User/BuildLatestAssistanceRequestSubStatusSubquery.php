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

        $query = DB::table($pivotTable)
            ->select("{$pivotTable}.assistance_id")
            ->selectRaw("MAX({$pivotTable}.recorded_at) as max_recorded_at")
            ->whereNull("{$pivotTable}.deleted_at");

        if ($programId !== null) {
            $query->whereIn("{$pivotTable}.assistance_id", function (Builder $subquery) use ($assistanceTable, $programId): void {
                $subquery
                    ->from($assistanceTable)
                    ->select('id')
                    ->where('program_id', $programId);
            });
        }

        return $query->groupBy("{$pivotTable}.assistance_id");
    }
}
