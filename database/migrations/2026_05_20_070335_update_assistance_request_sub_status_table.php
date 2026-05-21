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
        Schema::table('assistance_request_sub_status', function (Blueprint $table) {
            $table->timestamp('recorded_at')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistance_request_sub_status', function (Blueprint $table) {
            $table->dropColumn('recorded_at');
        });
    }
};
