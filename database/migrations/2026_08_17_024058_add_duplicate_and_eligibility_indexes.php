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
        Schema::table('individuals', function (Blueprint $table): void {
            $table->index(
                ['last_name', 'first_name', 'birthday'],
                'individuals_name_birthday_index',
            );
            $table->index(
                ['address_barangay_id', 'last_name'],
                'individuals_barangay_last_name_index',
            );
        });

        Schema::table('individual_identification', function (Blueprint $table): void {
            $table->index('number', 'individual_identification_number_index');
        });

        Schema::table('assistances', function (Blueprint $table): void {
            $table->index(
                ['beneficiary_id', 'program_id', 'was_delivered'],
                'assistances_beneficiary_program_delivered_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('individuals', function (Blueprint $table): void {
            $table->dropIndex('individuals_name_birthday_index');
            $table->dropIndex('individuals_barangay_last_name_index');
        });

        Schema::table('individual_identification', function (Blueprint $table): void {
            $table->dropIndex('individual_identification_number_index');
        });

        Schema::table('assistances', function (Blueprint $table): void {
            $table->dropIndex('assistances_beneficiary_program_delivered_index');
        });
    }
};
