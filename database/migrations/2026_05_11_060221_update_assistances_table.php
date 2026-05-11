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
            $table->renameColumn('dateVerified', 'date_verified');
            $table->renameColumn('dateRequested', 'date_requested');
            $table->renameColumn('dateDenied', 'date_denied');
            $table->renameColumn('dateDelivered', 'date_delivered');

            $table->bigInteger('project_id')->after('id')->unsigned()->change();
            $table->bigInteger('beneficiary_id')->after('project_id')->unsigned()->nullable()->change();
            $table->bigInteger('organization_id')->after('beneficiary_id')->unsigned()->nullable()->change();
            $table->bigInteger('mode_of_request_id')->after('organization_id')->unsigned()->nullable()->change();
            $table->bigInteger('user_id')->after('date_delivered')->unsigned()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistances', function (Blueprint $table) {
            $table->renameColumn('date_verified', 'dateVerified');
            $table->renameColumn('date_requested', 'dateRequested');
            $table->renameColumn('date_denied', 'dateDenied');
            $table->renameColumn('date_delivered', 'dateDelivered');
        });
    }
};
