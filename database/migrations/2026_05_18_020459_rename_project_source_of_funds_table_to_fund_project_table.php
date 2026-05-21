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
        Schema::table('fund_program', function (Blueprint $table) {
            $table->renameColumn('source_of_fund_id', 'fund_id');
            $table->dropForeign(['source_of_fund_id']);
            $table->foreign('fund_id')->references('id')->on('funds')->onDelete('cascade');

            $table->renameColumn('project_id', 'program_id');
            $table->dropForeign(['project_id']);
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
        });

        Schema::rename('project_source_of_fund', 'fund_program');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fund_program', function (Blueprint $table) {
            $table->renameColumn('fund_id', 'source_of_fund_id');
            $table->dropForeign(['fund_id']);
            $table->foreign('source_of_fund_id')->references('id')->on('funds')->onDelete('cascade');

            $table->renameColumn('program_id', 'project_id');
            $table->dropForeign(['program_id']);
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });

        Schema::rename('fund_program', 'project_source_of_fund');
    }
};
