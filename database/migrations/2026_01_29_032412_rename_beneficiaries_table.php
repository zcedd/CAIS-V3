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
            $table->renameColumn('firstName', 'first_name');
            $table->renameColumn('middleName', 'middle_name');
            $table->renameColumn('lastName', 'last_name');
            $table->renameColumn('suffix', 'suffix');
            $table->renameColumn('birthday', 'birth_date');
            $table->renameColumn('sex', 'gender');
            $table->renameColumn('mobileNumber', 'mobile_number');
            $table->renameColumn('indigenous', 'is_indigenous');
            $table->renameColumn('pwd', 'is_pwd');
            $table->renameColumn('brgy_id', 'address_barangay_id');
            $table->renameColumn('spouse', 'spouse_name');

            $table->foreignId('address_barangay_id')->nullable()->after('mobile_number')->change();
            $table->foreignId('civil_status_id')->nullable()->after('address_barangay_id')->change();

            $table->string('spouse_name')->nullable()->after('civil_status_id')->change();
            $table->string('ethnicity')->nullable()->after('spouse_name')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('individuals', 'beneficiaries');

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->renameColumn('first_name', 'firstName');
            $table->renameColumn('middle_name', 'middleName');
            $table->renameColumn('last_name', 'lastName');
            $table->renameColumn('suffix', 'suffix');
            $table->renameColumn('birth_date', 'birthday');
            $table->renameColumn('gender', 'sex');
            $table->renameColumn('mobile_number', 'mobileNumber');
            $table->renameColumn('is_indigenous', 'indigenous');
            $table->renameColumn('is_pwd', 'pwd');
            $table->renameColumn('address_barangay_id', 'brgy_id');
            $table->renameColumn('spouse_name', 'spouse');
        });
    }
};
