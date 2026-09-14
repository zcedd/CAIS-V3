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
        Schema::create('unspsc_codes', function (Blueprint $table): void {
            $table->id();
            $table->char('code', 8)->unique();
            $table->string('title');
            $table->string('level', 20);
            $table->foreignId('parent_id')->nullable()->constrained('unspsc_codes')->nullOnDelete();
            $table->char('segment_code', 8)->nullable();
            $table->char('family_code', 8)->nullable();
            $table->char('class_code', 8)->nullable();
            $table->boolean('is_curated')->default(false);
            $table->string('version')->default('curated-2026');
            $table->timestamps();

            $table->index(['level', 'is_curated']);
            $table->index('title');
            $table->index('segment_code');
            $table->index('family_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unspsc_codes');
    }
};
