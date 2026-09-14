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
        Schema::table('programs', function (Blueprint $table) {
            $table->string('kind')->default('standalone')->after('is_organization');
            $table->foreignId('parent_id')->nullable()->after('kind')->constrained('programs')->restrictOnDelete();
            $table->unsignedSmallInteger('batch_number')->nullable()->after('parent_id');
            $table->string('batch_name')->nullable()->after('batch_number');
            $table->index(['department_id', 'kind']);
            $table->index(['parent_id', 'is_closed']);
            $table->unique(['parent_id', 'batch_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropUnique(['parent_id', 'batch_number']);
            $table->dropIndex(['parent_id', 'is_closed']);
            $table->dropIndex(['department_id', 'kind']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['kind', 'batch_number', 'batch_name']);
        });
    }
};
