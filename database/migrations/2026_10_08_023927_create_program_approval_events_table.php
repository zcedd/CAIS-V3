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
        if (! Schema::hasTable('program_approval_events')) {
            Schema::create('program_approval_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action');
                $table->text('remark')->nullable();
                $table->timestamps();
            });

            return;
        }

        if (! Schema::hasColumn('program_approval_events', 'remark')) {
            Schema::table('program_approval_events', function (Blueprint $table) {
                $table->text('remark')->nullable()->after('action');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_approval_events');
    }
};
