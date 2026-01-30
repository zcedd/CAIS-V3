<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('assistance_item_release')) {
            return;
        }

        $defaultUserId = DB::table('users')->value('id');
        $now = now()->toDateTimeString();

        $existingKeys = DB::table('assistance_item_release')
            ->select('assistance_request_id', 'item_id')
            ->get()
            ->map(fn (object $r) => "{$r->assistance_request_id}-{$r->item_id}")
            ->flip()
            ->all();

        $rows = DB::table('assistance_request_items')
            ->where('assistance_request_items.is_delivered', true)
            ->whereNull('assistance_request_items.deleted_at')
            ->join('assistance_requests', 'assistance_request_items.assistance_request_id', '=', 'assistance_requests.id')
            ->whereNull('assistance_requests.deleted_at')
            ->select(
                'assistance_request_items.assistance_request_id',
                'assistance_request_items.item_id',
                'assistance_request_items.quantity',
                'assistance_request_items.specification',
                'assistance_requests.date_delivered',
                'assistance_requests.user_id'
            )
            ->get();

        $toInsert = [];
        foreach ($rows as $row) {
            $userId = $row->user_id ?? $defaultUserId;
            if ($userId === null) {
                continue;
            }

            $key = "{$row->assistance_request_id}-{$row->item_id}";
            if (isset($existingKeys[$key])) {
                continue;
            }
            $existingKeys[$key] = true;

            $dateReleased = $row->date_delivered
                ? date('Y-m-d', strtotime($row->date_delivered))
                : null;

            $toInsert[] = [
                'assistance_request_id' => $row->assistance_request_id,
                'item_id' => $row->item_id,
                'quantity' => $row->quantity ?? null,
                'date_released' => $dateReleased,
                'release_remarks' => $row->specification,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($toInsert, 500) as $chunk) {
            DB::table('assistance_item_release')->insert($chunk);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('assistance_item_release')) {
            return;
        }

        $ids = DB::table('assistance_item_release as r')
            ->join('assistance_request_items as i', function ($join): void {
                $join->on('i.assistance_request_id', '=', 'r.assistance_request_id')
                    ->on('i.item_id', '=', 'r.item_id')
                    ->where('i.is_delivered', '=', true);
            })
            ->pluck('r.id');

        DB::table('assistance_item_release')->whereIn('id', $ids)->delete();
    }
};
