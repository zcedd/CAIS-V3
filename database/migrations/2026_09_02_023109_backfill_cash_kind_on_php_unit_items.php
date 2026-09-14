<?php

use App\Enums\ItemKind;
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
            ->update(['kind' => ItemKind::Cash->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('items')
            ->where('kind', ItemKind::Cash->value)
            ->whereIn('item_unit_measurement_id', function ($query): void {
                $query->select('id')
                    ->from('item_unit_measurements')
                    ->whereRaw('lower(name) = ?', ['php']);
            })
            ->update(['kind' => ItemKind::Goods->value]);
    }
};
