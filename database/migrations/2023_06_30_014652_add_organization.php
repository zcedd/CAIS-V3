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
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('is_organization')->nullable()->default(false);
        });

        Schema::table('assistances', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained('organizations');
            $table->foreignId('mode_of_request_id')->nullable()->constrained('mode_of_requests');
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
