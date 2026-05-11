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
        Schema::create('assistance_request_sub_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistance_id')->constrained('assistances')->cascadeOnDelete();
            $table->foreignId('request_sub_status_id')->constrained('request_sub_statuses')->cascadeOnDelete();
            $table->longText('remark')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistance_request_sub_status');
    }
};
