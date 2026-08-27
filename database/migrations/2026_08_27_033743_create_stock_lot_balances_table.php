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
        Schema::create('stock_lot_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_lot_id')->unique()->constrained('stock_lots')->cascadeOnDelete();
            $table->unsignedInteger('on_hand')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_lot_balances');
    }
};
