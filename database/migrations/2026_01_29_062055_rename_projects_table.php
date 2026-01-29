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
        Schema::rename('projects', 'programs');

        Schema::table('programs', function (Blueprint $table) {
            $table->renameColumn('dateStarted', 'date_started');
            $table->renameColumn('dateEnded', 'date_ended');
            $table->renameColumn('source_of_fund_id', 'fund_id');

            $table->foreignId('department_id')->after('id')->change();
            $table->foreignId('fund_id')->nullable()->after('department_id')->change();
            $table->boolean('is_organization')->nullable()->after('fund_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('programs', 'projects');

        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('date_started', 'dateStarted');
            $table->renameColumn('date_ended', 'dateEnded');
            $table->renameColumn('fund_id', 'source_of_fund_id');
        });
    }
};
