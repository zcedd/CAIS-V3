<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shrink remark storage; values stay well under TEXT limits.
     */
    public function up(): void
    {
        if (Schema::hasTable('assistances') && Schema::hasColumn('assistances', 'remark')) {
            Schema::table('assistances', function (Blueprint $table): void {
                $table->text('remark')->nullable()->change();
            });
        }

        if (
            Schema::hasTable('assistance_request_sub_status')
            && Schema::hasColumn('assistance_request_sub_status', 'remark')
        ) {
            Schema::table('assistance_request_sub_status', function (Blueprint $table): void {
                $table->text('remark')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('assistances') && Schema::hasColumn('assistances', 'remark')) {
            Schema::table('assistances', function (Blueprint $table): void {
                $table->longText('remark')->nullable()->change();
            });
        }

        if (
            Schema::hasTable('assistance_request_sub_status')
            && Schema::hasColumn('assistance_request_sub_status', 'remark')
        ) {
            Schema::table('assistance_request_sub_status', function (Blueprint $table): void {
                $table->longText('remark')->nullable()->change();
            });
        }
    }
};
