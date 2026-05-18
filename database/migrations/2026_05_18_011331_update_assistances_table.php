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
            $table->renameColumn('beneficiary_id', 'individual_id');
            $table->dropForeign(['beneficiary_id']);
            $table->foreign('individual_id')->references('id')->on('individuals')->onDelete('cascade');

            $table->foreignId('beneficiary_id')->after('project_id')->nullable()->constrained('beneficiaries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistances', function (Blueprint $table) {
            $table->dropForeign(['beneficiary_id']);
            $table->dropColumn(['beneficiary_id']);

            $table->renameColumn('individual_id', 'beneficiary_id');
            $table->dropForeign(['individual_id']);
            $table->foreign('beneficiary_id')->references('id')->on('individuals')->onDelete('cascade');
        });
    }
};
