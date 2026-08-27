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
        Schema::table('items', function (Blueprint $table): void {
            $table->foreignId('unspsc_code_id')
                ->nullable()
                ->after('item_unit_measurement_id')
                ->constrained('unspsc_codes')
                ->nullOnDelete();
            $table->boolean('is_perishable')->default(false)->after('unspsc_code_id');
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('is_perishable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unspsc_code_id');
            $table->dropColumn(['is_perishable', 'low_stock_threshold']);
        });
    }
};
