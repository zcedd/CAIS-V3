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
        if (Schema::hasColumn('programs', 'requires_beneficiaries')) {
            return;
        }

        Schema::table('programs', function (Blueprint $table) {
            $table->boolean('requires_beneficiaries')
                ->default(true)
                ->after('approval_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('programs', 'requires_beneficiaries')) {
            return;
        }

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('requires_beneficiaries');
        });
    }
};
