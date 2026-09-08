<?php

use App\Services\Workflow\RequestStatusCatalog;
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
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('code');
            $table->boolean('is_terminal')->default(false)->after('sort_order');
            $table->boolean('is_hold')->default(false)->after('is_terminal');
            $table->boolean('pauses_sla')->default(false)->after('is_hold');
            $table->boolean('is_retired')->default(false)->after('pauses_sla');
        });

        Schema::table('request_sub_statuses', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
            $table->boolean('is_retired')->default(false)->after('description');
        });

        $catalog = app(RequestStatusCatalog::class);
        $catalog->ensure();
        $catalog->remapOpenAssistances();

        Schema::table('request_statuses', function (Blueprint $table) {
            $table->unique('code');
        });

        Schema::table('request_sub_statuses', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_sub_statuses', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'is_retired']);
        });

        Schema::table('request_statuses', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code',
                'sort_order',
                'is_terminal',
                'is_hold',
                'pauses_sla',
                'is_retired',
            ]);
        });
    }
};
