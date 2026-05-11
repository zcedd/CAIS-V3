<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // $table->foreignId('role_id')->constrained('roles');
            $table->foreignId('department_id')->constrained('departments');
        });

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->foreignId('brgy_id')->constrained('addrs_brgies');
        });

        Schema::table('addrs_brgies', function (Blueprint $table) {
            $table->foreignId('addrs_city_id')->constrained('addrs_cities');
        });

        Schema::table('assistances', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('beneficiary_id')->nullable()->constrained('beneficiaries');
            $table->foreignId('user_id')->constrained('users');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('department_id')->constrained('departments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
