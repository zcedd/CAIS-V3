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
        Schema::table('assistances', function (Blueprint $table) {
            $table->index(
                ['program_id', 'date_requested'],
                'assistances_program_id_date_requested_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistances', function (Blueprint $table) {
            $table->dropIndex('assistances_program_id_date_requested_index');
        });
    }
};
