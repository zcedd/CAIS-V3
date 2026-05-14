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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('cais_number')->unique();
            $table->string('name');
            $table->string('mobile_number')->nullable();
            $table->foreignId('beneficiary_id')->constrained('beneficiaries');
            $table->foreignId('addrs_brgy_id')->constrained('addrs_brgies');
            $table->integer('total_member')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('organizations');
    }
};
