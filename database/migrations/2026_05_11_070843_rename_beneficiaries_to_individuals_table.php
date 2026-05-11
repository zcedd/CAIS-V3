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
        Schema::rename('beneficiaries', 'individuals');

        Schema::table('individuals', function (Blueprint $table) {
            $table->renameColumn('firstName', 'first_name')->change();
            $table->renameColumn('middleName', 'middle_name')->change();
            $table->renameColumn('lastName', 'last_name')->change();
            $table->renameColumn('mobileNumber', 'mobile_number')->change();
            $table->renameColumn('brgy_id', 'address_barangay_id')->change();
            $table->bigInteger('address_barangay_id')->after('is_solo_parent')->unsigned()->change();
            $table->bigInteger('civil_status_id')->after('address_barangay_id')->unsigned()->nullable()->change();
            $table->string('ethnicity')->after('civil_status_id')->nullable()->change();
            $table->string('spouse')->after('ethnicity')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('individuals', 'beneficiaries');

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->renameColumn('first_name', 'firstName')->change();
            $table->renameColumn('middle_name', 'middleName')->change();
            $table->renameColumn('last_name', 'lastName')->change();
            $table->renameColumn('mobile_number', 'mobileNumber')->change();
            $table->renameColumn('address_barangay_id', 'brgy_id')->change();
            $table->bigInteger('brgy_id')->after('is_solo_parent')->unsigned()->change();
        });
    }
};
