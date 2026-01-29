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
        Schema::rename('assistance_item', 'assistance_request_items');

        Schema::table('assistance_request_items', function (Blueprint $table) {
            $table->renameColumn('is_received', 'is_delivered');
            $table->renameColumn('assistance_id', 'assistance_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('assistance_request_items', 'assistance_item');

        Schema::table('assistance_item', function (Blueprint $table) {
            $table->renameColumn('is_delivered', 'is_received');
            $table->renameColumn('assistance_request_id', 'assistance_id');
        });
    }
};
