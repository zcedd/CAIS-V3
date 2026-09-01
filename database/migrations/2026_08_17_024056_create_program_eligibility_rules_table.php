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
        Schema::create('program_eligibility_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->unsignedInteger('cooldown_days')->nullable();
            $table->boolean('require_pwd')->default(false);
            $table->boolean('require_4ps')->default(false);
            $table->boolean('require_solo_parent')->default(false);
            $table->boolean('require_indigenous')->default(false);
            $table->timestamps();

            $table->unique('program_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_eligibility_rules');
    }
};
