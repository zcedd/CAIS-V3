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
        Schema::table('individuals', function (Blueprint $table): void {
            $table->string('everify_status', 20)->default('skipped')->after('address_barangay_id');
            $table->timestamp('everify_verified_at')->nullable()->after('everify_status');
            $table->unsignedTinyInteger('everify_result_grade')->nullable()->after('everify_verified_at');
            $table->string('everify_query_log_id', 64)->nullable()->after('everify_result_grade');
        });

        Schema::table('everify_logs', function (Blueprint $table): void {
            $table->dropColumn(['request', 'response']);
        });

        Schema::table('everify_logs', function (Blueprint $table): void {
            $table->string('purpose', 20)->default('query')->after('user_id');
            $table->string('query_log_id', 64)->nullable()->after('status_code');
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->foreignId('individual_id')
                ->nullable()
                ->after('user_id')
                ->constrained('individuals')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('everify_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('individual_id');
            $table->dropColumn(['purpose', 'query_log_id', 'request', 'response']);
        });

        Schema::table('everify_logs', function (Blueprint $table): void {
            $table->json('request')->nullable();
            $table->json('response')->nullable();
        });

        Schema::table('individuals', function (Blueprint $table): void {
            $table->dropColumn([
                'everify_status',
                'everify_verified_at',
                'everify_result_grade',
                'everify_query_log_id',
            ]);
        });
    }
};
