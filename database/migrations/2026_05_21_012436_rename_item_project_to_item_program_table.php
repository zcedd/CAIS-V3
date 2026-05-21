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
        Schema::table('item_project', function (Blueprint $table) {
            $table->renameColumn('project_id', 'program_id');
            $table->dropForeign(['project_id']);
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
        });

        Schema::rename('item_project', 'item_program');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_program', function (Blueprint $table) {
            $table->renameColumn('program_id', 'project_id');
            $table->dropForeign(['program_id']);
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });

        Schema::rename('item_program', 'item_project');
    }
};
