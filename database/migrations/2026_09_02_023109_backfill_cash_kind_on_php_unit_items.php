<?php

use App\Support\ItemKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('items')
            ->whereIn('item_unit_measurement_id', function ($query): void {
                $query->select('id')
                    ->from('item_unit_measurements')
                    ->whereRaw('lower(name) = ?', ['php']);
            })
            ->update(['kind' => ItemKind::Cash]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('items')
            ->where('kind', ItemKind::Cash)
            ->whereIn('item_unit_measurement_id', function ($query): void {
                $query->select('id')
                    ->from('item_unit_measurements')
                    ->whereRaw('lower(name) = ?', ['php']);
            })
            ->update(['kind' => ItemKind::Goods]);
    }
};
