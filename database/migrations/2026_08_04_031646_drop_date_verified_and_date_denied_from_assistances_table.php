<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Verified/denied milestones live in assistance_request_sub_status history.
     * date_delivered remains as a denormalized mirror for delivery metrics.
     */
    public function up(): void
    {
        $columns = collect(['date_verified', 'date_denied'])
            ->filter(static fn (string $column): bool => Schema::hasColumn('assistances', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        Schema::table('assistances', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistances', function (Blueprint $table) {
            if (! Schema::hasColumn('assistances', 'date_verified')) {
                $table->date('date_verified')->nullable()->after('date_requested');
            }

            if (! Schema::hasColumn('assistances', 'date_denied')) {
                $table->date('date_denied')->nullable()->after('date_delivered');
            }
        });
    }
};
