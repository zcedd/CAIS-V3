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
        Schema::rename('assistances', 'assistance_requests');

        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->renameColumn('dateRequested', 'date_requested');
            $table->renameColumn('dateVerified', 'date_verified');
            $table->renameColumn('dateDenied', 'date_denied');
            $table->renameColumn('dateDelivered', 'date_delivered');
            $table->renameColumn('project_id', 'program_id');
            $table->renameColumn('beneficiary_id', 'individual_id');

            $table->foreignId('program_id')->after('id')->change();
            $table->foreignId('individual_id')->nullable()->after('program_id')->change();
            $table->foreignId('organization_id')->nullable()->after('individual_id')->change();
            $table->foreignId('mode_of_request_id')->nullable()->after('organization_id')->change();
            $table->foreignId('user_id')->nullable()->after('mode_of_request_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('assistance_requests', 'assistances');

        Schema::table('assistances', function (Blueprint $table) {
            $table->renameColumn('date_requested', 'dateRequested');
            $table->renameColumn('date_verified', 'dateVerified');
            $table->renameColumn('date_denied', 'dateDenied');
            $table->renameColumn('date_delivered', 'dateDelivered');
            $table->renameColumn('program_id', 'project_id');
            $table->renameColumn('individual_id', 'beneficiary_id');
        });
    }
};
