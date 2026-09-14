<?php

use App\Models\UnspscCode;
use App\Support\UnspscCodeImporter;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed a curated UNSPSC subset for social assistance classification.
     *
     * UNSPSC codes are maintained by GS1. Confirm version and usage terms before
     * bundling a commercial full code list. This migration only loads the
     * internally curated CSV shipped with CAIS.
     */
    public function up(): void
    {
        $path = database_path('csv/unspsc_curated.csv');

        (new UnspscCodeImporter)->importFromCsv($path, markCurated: true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        UnspscCode::query()->where('version', 'curated-2026')->delete();
    }
};
