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
        Schema::create('distribution_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_operation_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('distribution_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('distribution_batch_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('distribution_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status');
            $table->timestamp('device_captured_at')->nullable();
            $table->json('result')->nullable();
            $table->json('error')->nullable();
            $table->timestamps();

            $table->index(['distribution_batch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribution_sync_operations');
    }
};
