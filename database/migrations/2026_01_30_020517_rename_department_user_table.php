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
        Schema::rename('department_user', 'department_supervisors');

        Schema::table('department_supervisors', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('department_supervisors', 'department_user');

        Schema::table('department_user', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
