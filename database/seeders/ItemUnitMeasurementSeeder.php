<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\ItemUnitMeasurement;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ItemUnitMeasurementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        ItemUnitMeasurement::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $units = array(
            'pc' => ['name' => 'pc', 'created_at' => now(), 'updated_at' => now()],
            'pair' => ['name' => 'pair', 'created_at' => now(), 'updated_at' => now()],
            'set' => ['name' => 'set', 'created_at' => now(), 'updated_at' => now()],
            'unit' => ['name' => 'unit', 'created_at' => now(), 'updated_at' => now()],
            'item' => ['name' => 'item', 'created_at' => now(), 'updated_at' => now()],
            'lot' => ['name' => 'lot', 'created_at' => now(), 'updated_at' => now()],
            'bundle' => ['name' => 'bundle', 'created_at' => now(), 'updated_at' => now()],
            'pack' => ['name' => 'pack', 'created_at' => now(), 'updated_at' => now()],
            'box' => ['name' => 'box', 'created_at' => now(), 'updated_at' => now()],
            'case' => ['name' => 'case', 'created_at' => now(), 'updated_at' => now()],
            'carton' => ['name' => 'carton', 'created_at' => now(), 'updated_at' => now()],
            'dozen' => ['name' => 'dozen', 'created_at' => now(), 'updated_at' => now()],
            'gross' => ['name' => 'gross', 'created_at' => now(), 'updated_at' => now()],
            'score' => ['name' => 'score', 'created_at' => now(), 'updated_at' => now()],
            'tray' => ['name' => 'tray', 'created_at' => now(), 'updated_at' => now()],
            'sack' => ['name' => 'sack', 'created_at' => now(), 'updated_at' => now()],
            'bag' => ['name' => 'bag', 'created_at' => now(), 'updated_at' => now()],
            'pouch' => ['name' => 'pouch', 'created_at' => now(), 'updated_at' => now()],
            'packet' => ['name' => 'packet', 'created_at' => now(), 'updated_at' => now()],
            'blister pack' => ['name' => 'blister pack', 'created_at' => now(), 'updated_at' => now()],
            'strip' => ['name' => 'strip', 'created_at' => now(), 'updated_at' => now()],
            'bottle' => ['name' => 'bottle', 'created_at' => now(), 'updated_at' => now()],
            'can' => ['name' => 'can', 'created_at' => now(), 'updated_at' => now()],
            'tin' => ['name' => 'tin', 'created_at' => now(), 'updated_at' => now()],
            'jar' => ['name' => 'jar', 'created_at' => now(), 'updated_at' => now()],
            'tub' => ['name' => 'tub', 'created_at' => now(), 'updated_at' => now()],
            'tube' => ['name' => 'tube', 'created_at' => now(), 'updated_at' => now()],
            'gallon' => ['name' => 'gallon', 'created_at' => now(), 'updated_at' => now()],
            'liter' => ['name' => 'liter', 'created_at' => now(), 'updated_at' => now()],
            'milliliter' => ['name' => 'milliliter', 'created_at' => now(), 'updated_at' => now()],
            'drum' => ['name' => 'drum', 'created_at' => now(), 'updated_at' => now()],
            'barrel' => ['name' => 'barrel', 'created_at' => now(), 'updated_at' => now()],
            'bale' => ['name' => 'bale', 'created_at' => now(), 'updated_at' => now()],
            'bushel' => ['name' => 'bushel', 'created_at' => now(), 'updated_at' => now()],
            'crate' => ['name' => 'crate', 'created_at' => now(), 'updated_at' => now()],
            'bunch' => ['name' => 'bunch', 'created_at' => now(), 'updated_at' => now()],
            'bundle' => ['name' => 'bundle', 'created_at' => now(), 'updated_at' => now()],
            'ream' => ['name' => 'ream', 'created_at' => now(), 'updated_at' => now()],
            'quire' => ['name' => 'require', 'created_at' => now(), 'updated_at' => now()],
            'pad' => ['name' => 'pad', 'created_at' => now(), 'updated_at' => now()],
            'roll' => ['name' => 'roll', 'created_at' => now(), 'updated_at' => now()],
            'sheet' => ['name' => 'sheet', 'created_at' => now(), 'updated_at' => now()],
            'mg' => ['name' => 'mg', 'created_at' => now(), 'updated_at' => now()],
            'g' => ['name' => 'g', 'created_at' => now(), 'updated_at' => now()],
            'kg' => ['name' => 'kg', 'created_at' => now(), 'updated_at' => now()],
            'ton' => ['name' => 'ton', 'created_at' => now(), 'updated_at' => now()],
            'lb' => ['name' => 'lb', 'created_at' => now(), 'updated_at' => now()],
            'oz' => ['name' => 'oz', 'created_at' => now(), 'updated_at' => now()],
            'quintal' => ['name' => 'quintal', 'created_at' => now(), 'updated_at' => now()],
            'mm' => ['name' => 'mm', 'created_at' => now(), 'updated_at' => now()],
            'cm' => ['name' => 'cm', 'created_at' => now(), 'updated_at' => now()],
            'm' => ['name' => 'm', 'created_at' => now(), 'updated_at' => now()],
            'km' => ['name' => 'km', 'created_at' => now(), 'updated_at' => now()],
            'inch' => ['name' => 'inch', 'created_at' => now(), 'updated_at' => now()],
            'foot' => ['name' => 'foot', 'created_at' => now(), 'updated_at' => now()],
            'yard' => ['name' => 'yard', 'created_at' => now(), 'updated_at' => now()],
        );

        DB::table('item_unit_measurements')->insert($units);
    }
}
