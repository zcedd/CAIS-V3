<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sub status names used to resolve IDs (avoids hardcoding IDs across environments).
     */
    private const SUB_STATUS_NAMES = [
        'requested' => 'Awaiting Review',
        'verified' => 'Verified',
        'delivered' => 'Successfully Delivered',
        'denied' => 'Closed after Denial',
    ];

    /**
     * Run the migrations.
     *
     * Fills assistance_request_sub_statuses from assistance_requests using
     * date_denied, date_delivered, date_verified, and date_requested.
     * Priority: denied > delivered > verified > requested.
     */
    public function up(): void
    {
        if (! $this->tableExists()) {
            return;
        }

        $statusIds = $this->resolveSubStatusIds();
        if (count($statusIds) !== 4) {
            return;
        }

        $existingIds = DB::table('assistance_request_sub_statuses')
            ->pluck('assistance_request_id')
            ->unique()
            ->flip()
            ->all();

        $rows = DB::table('assistance_requests')
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->whereNotNull('date_requested')
                    ->orWhereNotNull('date_verified')
                    ->orWhereNotNull('date_delivered')
                    ->orWhereNotNull('date_denied');
            })
            ->select(
                'id',
                'date_requested',
                'date_verified',
                'date_delivered',
                'date_denied'
            )
            ->get();

        $now = now()->toDateTimeString();
        $toInsert = [];

        foreach ($rows as $row) {
            if (isset($existingIds[$row->id])) {
                continue;
            }

            $derived = $this->deriveSubStatus($row);
            if ($derived === null) {
                continue;
            }

            $requestSubStatusId = $statusIds[$derived['key']];
            $pivotCreatedAt = $derived['date'] ? date('Y-m-d H:i:s', strtotime($derived['date'])) : $now;

            $toInsert[] = [
                'assistance_request_id' => $row->id,
                'request_sub_status_id' => $requestSubStatusId,
                'remark' => null,
                'created_at' => $pivotCreatedAt,
                'updated_at' => $now,
            ];
        }

        if ($toInsert !== []) {
            foreach (array_chunk($toInsert, 500) as $chunk) {
                DB::table('assistance_request_sub_statuses')->insertOrIgnore($chunk);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->tableExists()) {
            return;
        }

        $statusIds = $this->resolveSubStatusIds();
        if (count($statusIds) !== 4) {
            return;
        }

        DB::table('assistance_request_sub_statuses')
            ->whereIn('request_sub_status_id', array_values($statusIds))
            ->delete();
    }

    private function tableExists(): bool
    {
        return Schema::hasTable('assistance_request_sub_statuses')
            && Schema::hasTable('assistance_requests')
            && Schema::hasTable('request_sub_statuses');
    }

    /**
     * @return array<string, int>
     */
    private function resolveSubStatusIds(): array
    {
        $records = DB::table('request_sub_statuses')
            ->whereIn('name', array_values(self::SUB_STATUS_NAMES))
            ->pluck('id', 'name');

        $byKey = [];
        foreach (self::SUB_STATUS_NAMES as $key => $name) {
            if (isset($records[$name])) {
                $byKey[$key] = (int) $records[$name];
            }
        }

        return $byKey;
    }

    /**
     * Derive sub status key and representative date from assistance_request row.
     * Priority: denied > delivered > verified > requested.
     *
     * @return array{key: string, date: string|null}|null
     */
    private function deriveSubStatus(object $row): ?array
    {
        if (! empty($row->date_denied)) {
            return ['key' => 'denied', 'date' => $row->date_denied];
        }
        if (! empty($row->date_delivered)) {
            return ['key' => 'delivered', 'date' => $row->date_delivered];
        }
        if (! empty($row->date_verified)) {
            return ['key' => 'verified', 'date' => $row->date_verified];
        }
        if (! empty($row->date_requested)) {
            return ['key' => 'requested', 'date' => $row->date_requested];
        }

        return null;
    }
};
