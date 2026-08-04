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
        Schema::create('assistance_field_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assistance_id')->constrained('assistances')->cascadeOnDelete();
            $table->foreignId('program_field_id')->constrained('program_fields')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['assistance_id', 'program_field_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistance_field_values');
    }
};
