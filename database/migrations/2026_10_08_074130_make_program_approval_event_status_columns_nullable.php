<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('program_approval_events') && Schema::hasColumn('program_approval_events', 'from_status')) {
            Schema::table('program_approval_events', function (Blueprint $table): void {
                $table->string('from_status')->nullable()->change();
            });
        }

        if (Schema::hasTable('program_approval_events') && Schema::hasColumn('program_approval_events', 'to_status')) {
            Schema::table('program_approval_events', function (Blueprint $table): void {
                $table->string('to_status')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('program_approval_events') && Schema::hasColumn('program_approval_events', 'from_status')) {
            Schema::table('program_approval_events', function (Blueprint $table): void {
                $table->string('from_status')->nullable(false)->change();
            });
        }

        if (Schema::hasTable('program_approval_events') && Schema::hasColumn('program_approval_events', 'to_status')) {
            Schema::table('program_approval_events', function (Blueprint $table): void {
                $table->string('to_status')->nullable(false)->change();
            });
        }
    }
};
