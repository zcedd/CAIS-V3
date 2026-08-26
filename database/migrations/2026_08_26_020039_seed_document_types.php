<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('document_types')->insert([
            ['name' => 'Valid ID', 'slug' => 'valid_id', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Certificate of Indigency', 'slug' => 'indigency_certificate', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Delivery Photo', 'slug' => 'delivery_photo', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Signed Acknowledgment', 'slug' => 'signed_acknowledgment', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Other', 'slug' => 'other', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('document_types')->whereIn('slug', [
            'valid_id',
            'indigency_certificate',
            'delivery_photo',
            'signed_acknowledgment',
            'other',
        ])->delete();
    }
};
