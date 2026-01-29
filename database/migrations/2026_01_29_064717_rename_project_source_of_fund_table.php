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
        Schema::rename('project_source_of_fund', 'program_funds');

        Schema::table('program_funds', function (Blueprint $table) {
            $table->renameColumn('source_of_fund_id', 'fund_id');
            $table->renameColumn('project_id', 'program_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('program_funds', 'project_source_of_fund');

        Schema::table('program_funds', function (Blueprint $table) {
            $table->renameColumn('fund_id', 'source_of_fund_id');
            $table->renameColumn('program_id', 'project_id');
        });
    }
};
