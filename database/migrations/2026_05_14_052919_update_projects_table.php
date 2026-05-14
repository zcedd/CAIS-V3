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
        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('dateStarted', 'start_at');
            $table->renameColumn('dateEnded', 'end_at');

            $table->bigInteger('department_id')->after('id')->unsigned()->nullable()->change();
            $table->boolean('is_organization')->after('is_request_only')->nullable()->default(false)->change();
            $table->bigInteger('source_of_fund_id')->after('is_organization')->unsigned()->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('start_at', 'dateStarted');
            $table->renameColumn('end_at', 'dateEnded');

            $table->bigInteger('department_id')->after('id')->unsigned()->nullable()->change();
            $table->boolean('is_organization')->after('is_request_only')->nullable()->default(false)->change();
            $table->bigInteger('source_of_fund_id')->after('is_organization')->unsigned()->nullable()->change();
        });
    }
};
