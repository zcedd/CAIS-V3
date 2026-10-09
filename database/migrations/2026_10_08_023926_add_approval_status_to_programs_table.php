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
        if (Schema::hasColumn('programs', 'approval_status')) {
            return;
        }

        Schema::table('programs', function (Blueprint $table) {
            $table->string('approval_status')
                ->default('approved')
                ->after('public_intake');
            $table->index('approval_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('programs', 'approval_status')) {
            return;
        }

        Schema::table('programs', function (Blueprint $table) {
            $table->dropIndex(['approval_status']);
            $table->dropColumn('approval_status');
        });
    }
};
