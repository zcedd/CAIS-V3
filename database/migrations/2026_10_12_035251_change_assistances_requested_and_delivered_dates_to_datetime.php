<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the sub-status time on the assistance request and delivery dates.
     *
     * Rolling back changes the columns back to dates and drops the time.
     */
    public function up(): void
    {
        Schema::table('assistances', function (Blueprint $table) {
            $table->dateTime('date_requested')->nullable()->change();
            $table->dateTime('date_delivered')->nullable()->change();
        });

        $this->backfillRequestedTimes();
        $this->backfillDeliveredTimes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistances', function (Blueprint $table) {
            $table->date('date_requested')->nullable()->change();
            $table->date('date_delivered')->nullable()->change();
        });
    }

    private function backfillRequestedTimes(): void
    {
        DB::table('assistances')
            ->whereNotNull('date_requested')
            ->orderBy('id')
            ->select(['id', 'date_requested'])
            ->chunkById(500, function (Collection $assistances): void {
                $recorded = DB::table('assistance_request_sub_status')
                    ->whereIn('assistance_id', $assistances->pluck('id'))
                    ->whereNull('deleted_at')
                    ->get(['assistance_id', 'recorded_at']);

                $earliestOnDay = [];

                foreach ($recorded as $row) {
                    $key = $row->assistance_id.'|'.Carbon::parse($row->recorded_at)->toDateString();
                    $current = $earliestOnDay[$key] ?? null;

                    if ($current === null || $row->recorded_at < $current) {
                        $earliestOnDay[$key] = $row->recorded_at;
                    }
                }

                foreach ($assistances as $assistance) {
                    $key = $assistance->id.'|'.Carbon::parse($assistance->date_requested)->toDateString();
                    $recordedAt = $earliestOnDay[$key] ?? null;

                    if ($recordedAt === null || (string) $recordedAt === (string) $assistance->date_requested) {
                        continue;
                    }

                    DB::table('assistances')->where('id', $assistance->id)->update([
                        'date_requested' => $recordedAt,
                    ]);
                }
            });
    }

    private function backfillDeliveredTimes(): void
    {
        DB::table('assistances')
            ->orderBy('id')
            ->select(['id', 'date_delivered'])
            ->chunkById(500, function (Collection $assistances): void {
                $delivered = DB::table('assistance_request_sub_status as arss')
                    ->join('request_sub_statuses as rss', 'rss.id', '=', 'arss.request_sub_status_id')
                    ->join('request_statuses as rs', 'rs.id', '=', 'rss.request_status_id')
                    ->whereIn('arss.assistance_id', $assistances->pluck('id'))
                    ->whereNull('arss.deleted_at')
                    ->where(function ($query): void {
                        $query->where('rs.code', 'delivered')
                            ->orWhere('rs.name', 'Delivered');
                    })
                    ->groupBy('arss.assistance_id')
                    ->selectRaw('arss.assistance_id, MIN(arss.recorded_at) as recorded_at')
                    ->get()
                    ->keyBy('assistance_id');

                foreach ($assistances as $assistance) {
                    $recordedAt = $delivered->get($assistance->id)?->recorded_at;

                    if ($recordedAt === null) {
                        continue;
                    }

                    if ($assistance->date_delivered === null) {
                        DB::table('assistances')->where('id', $assistance->id)->update([
                            'date_delivered' => $recordedAt,
                        ]);

                        continue;
                    }

                    $sameDay = Carbon::parse($assistance->date_delivered)->toDateString()
                        === Carbon::parse($recordedAt)->toDateString();

                    if ($sameDay && (string) $assistance->date_delivered !== (string) $recordedAt) {
                        DB::table('assistances')->where('id', $assistance->id)->update([
                            'date_delivered' => $recordedAt,
                        ]);
                    }
                }
            });
    }
};
