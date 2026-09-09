<?php

use App\Enums\AssistanceItemOrigin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assistance_item', function (Blueprint $table): void {
            $table->string('origin', 20)
                ->default(AssistanceItemOrigin::Requested->value)
                ->after('item_id');
            $table->unsignedInteger('requested_quantity')
                ->default(0)
                ->after('quantity');
            $table->foreignId('substituted_for_assistance_item_id')
                ->nullable()
                ->after('requested_quantity')
                ->constrained('assistance_item')
                ->nullOnDelete();
            $table->timestamp('substituted_at')
                ->nullable()
                ->after('substituted_for_assistance_item_id');
            $table->string('fulfillment_reason')
                ->nullable()
                ->after('substituted_at');

            $table->index(
                ['assistance_id', 'origin', 'deleted_at'],
                'assistance_item_assistance_origin_deleted_index',
            );
        });

        DB::table('assistance_item')->update([
            'requested_quantity' => DB::raw('quantity'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistance_item', function (Blueprint $table): void {
            $table->dropIndex('assistance_item_assistance_origin_deleted_index');
            $table->dropConstrainedForeignId('substituted_for_assistance_item_id');
            $table->dropColumn([
                'origin',
                'requested_quantity',
                'substituted_at',
                'fulfillment_reason',
            ]);
        });
    }
};
