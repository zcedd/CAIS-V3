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
        Schema::rename('beneficiary_identification', 'individual_identification');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('individual_identification', 'beneficiary_identification');
    }
};
