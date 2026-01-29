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
        Schema::rename('assistance_request_sub_status', 'assistance_request_sub_statuses');

        Schema::table('assistance_request_sub_statuses', function (Blueprint $table) {
            $table->renameColumn('assistance_id', 'assistance_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('assistance_request_sub_statuses', 'assistance_request_sub_status');

        Schema::table('assistance_request_sub_status', function (Blueprint $table) {
            $table->renameColumn('assistance_request_id', 'assistance_id');
        });
    }
};
