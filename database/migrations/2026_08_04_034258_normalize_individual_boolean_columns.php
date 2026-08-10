<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $columns = [
        'pwd',
        'indigenous',
        'is_4ps_beneficiary',
        'is_solo_parent',
    ];

    /**
     * Treat NULL the same as false for demographic flags.
     */
    public function up(): void
    {
        if (! Schema::hasTable('individuals')) {
            return;
        }

        foreach ($this->columns as $column) {
            if (! Schema::hasColumn('individuals', $column)) {
                continue;
            }

            DB::table('individuals')->whereNull($column)->update([$column => false]);
        }

        Schema::table('individuals', function (Blueprint $table): void {
            foreach ($this->columns as $column) {
                if (! Schema::hasColumn('individuals', $column)) {
                    continue;
                }

                $table->boolean($column)->default(false)->nullable(false)->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('individuals')) {
            return;
        }

        Schema::table('individuals', function (Blueprint $table): void {
            foreach ($this->columns as $column) {
                if (! Schema::hasColumn('individuals', $column)) {
                    continue;
                }

                $table->boolean($column)->nullable()->default(false)->change();
            }
        });
    }
};
