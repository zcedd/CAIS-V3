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
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained('stock_lots')->nullOnDelete();
            $table->string('type', 32);
            $table->unsignedInteger('quantity');
            $table->foreignId('assistance_item_id')->nullable()->constrained('assistance_item')->nullOnDelete();
            $table->foreignId('reverses_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['department_id', 'item_id', 'type']);
            $table->index('assistance_item_id');
            $table->index('program_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
