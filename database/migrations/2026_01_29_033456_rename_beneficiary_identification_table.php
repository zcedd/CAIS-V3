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
        Schema::rename('beneficiary_identification', 'individual_identifications');

        Schema::table('individual_identifications', function (Blueprint $table) {
            $table->renameColumn('beneficiary_id', 'individual_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('individual_identifications', 'beneficiary_identification');

        Schema::table('beneficiary_identification', function (Blueprint $table) {
            $table->renameColumn('individual_id', 'beneficiary_id');
        });
    }
};
