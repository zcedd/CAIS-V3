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
        Schema::rename('beneficiary_organization', 'organization_members');

        Schema::table('organization_members', function (Blueprint $table) {
            $table->renameColumn('beneficiary_id', 'individual_id');

            $table->foreignId('organization_id')->nullable()->after('id')->change();
            $table->string('role')->nullable()->after('individual_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('organization_members', 'beneficiary_organization');

        Schema::table('beneficiary_organization', function (Blueprint $table) {
            $table->renameColumn('individual_id', 'beneficiary_id');
            $table->dropColumn('role');
        });
    }
};
