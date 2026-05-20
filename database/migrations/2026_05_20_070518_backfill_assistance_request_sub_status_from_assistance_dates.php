<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Map each legacy assistances date column to one or more sub-statuses.
     *
     * @var array<string, array<int, string>>
     */
    private array $columnToSubStatuses = [
        'date_requested' => ['Awaiting Review'],
        'date_verified' => ['Verified'],
        'date_delivered' => ['Successfully Delivered', 'Closed after Resolution'],
        'date_denied' => ['Eligibility Denied', 'Closed after Denial'],
    ];

    /**
     * Run the migrations.
     *
     * Backfills the assistance_request_sub_status pivot table from the legacy
     * date_* columns on assistances. Uses INSERT...SELECT so the operation
     * scales to the full assistances table without hydrating rows into PHP.
     */
    public function up(): void
    {
        $subStatusIds = $this->resolveSubStatusIds();

        DB::transaction(function () use ($subStatusIds): void {
            foreach ($this->columnToSubStatuses as $column => $subStatusNames) {
                foreach ($subStatusNames as $subStatusName) {
                    $subStatusId = $subStatusIds[$subStatusName] ?? null;

                    if ($subStatusId === null) {
                        continue;
                    }

                    $this->backfillColumn($column, $subStatusId);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Removes only pivot rows that exactly mirror the legacy date columns so we
     * never delete sub-status entries that were recorded through the new flow.
     */
    public function down(): void
    {
        $subStatusIds = $this->resolveSubStatusIds();

        DB::transaction(function () use ($subStatusIds): void {
            foreach ($this->columnToSubStatuses as $column => $subStatusNames) {
                foreach ($subStatusNames as $subStatusName) {
                    $subStatusId = $subStatusIds[$subStatusName] ?? null;

                    if ($subStatusId === null) {
                        continue;
                    }

                    $this->rollbackColumn($column, $subStatusId);
                }
            }
        });
    }

    /**
     * Legacy assistances.date_* values must fit pivot timestamps() columns. MySQL
     * rejects years before 1000 for DATETIME and has a narrower range for TIMESTAMP
     * (e.g. bogus 0022-12-09 from bad imports).
     */
    private function whereLegacyDateStorableAsPivotTimestamp(Builder $query, string $column): void
    {
        $qualified = 'a.`'.$column.'`';

        $query->whereRaw($qualified.' >= ?', ['1970-01-01'])
            ->whereRaw($qualified.' <= ?', ['2038-01-18']);
    }

    private function backfillColumn(string $column, int $subStatusId): void
    {
        DB::table('assistance_request_sub_status')->insertUsing(
            ['assistance_id', 'request_sub_status_id', 'remark', 'created_at', 'updated_at', 'deleted_at'],
            function (Builder $query) use ($column, $subStatusId): void {
                $query->from('assistances as a')
                    ->leftJoin('assistance_request_sub_status as existing', function (JoinClause $join) use ($subStatusId): void {
                        $join->on('existing.assistance_id', '=', 'a.id')
                            ->where('existing.request_sub_status_id', '=', $subStatusId);
                    })
                    ->whereNotNull('a.'.$column);

                $this->whereLegacyDateStorableAsPivotTimestamp($query, $column);

                $query->whereNull('existing.id')
                    ->select([
                        'a.id',
                        new Expression((string) $subStatusId),
                        new Expression('NULL'),
                        'a.'.$column,
                        'a.'.$column,
                        new Expression('NULL'),
                    ]);
            }
        );
    }

    private function rollbackColumn(string $column, int $subStatusId): void
    {
        DB::table('assistance_request_sub_status as pivot')
            ->join('assistances as a', 'a.id', '=', 'pivot.assistance_id')
            ->where('pivot.request_sub_status_id', $subStatusId)
            ->whereNull('pivot.remark')
            ->whereColumn('pivot.created_at', 'a.'.$column)
            ->delete();
    }

    /**
     * Build a name => id lookup for the sub-statuses used by this backfill.
     *
     * @return array<string, int>
     */
    private function resolveSubStatusIds(): array
    {
        return DB::table('request_sub_statuses')
            ->whereIn(
                'name',
                collect($this->columnToSubStatuses)
                    ->flatten()
                    ->values()
                    ->all()
            )
            ->pluck('id', 'name')
            ->map(static fn (int $id): int => $id)
            ->all();
    }
};
