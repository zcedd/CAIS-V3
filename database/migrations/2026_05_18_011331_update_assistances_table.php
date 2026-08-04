<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MORPH_BENEFICIARY_FOREIGN_KEY = 'assistances_morph_beneficiary_id_foreign';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('assistances', 'beneficiary_id') && ! Schema::hasColumn('assistances', 'individual_id')) {
            Schema::table('assistances', function (Blueprint $table) {
                // $table->dropForeign(['beneficiary_id']);
            });

            Schema::table('assistances', function (Blueprint $table) {
                $table->renameColumn('beneficiary_id', 'individual_id');
            });
        }

        if (! $this->foreignKeyExists('assistances', 'individual_id', 'individuals')) {
            Schema::table('assistances', function (Blueprint $table) {
                $table->foreign('individual_id')
                    ->references('id')
                    ->on('individuals')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasColumn('assistances', 'beneficiary_id')) {
            Schema::table('assistances', function (Blueprint $table) {
                $table->foreignId('beneficiary_id')
                    ->after('project_id')
                    ->nullable()
                    ->constrained('beneficiaries', 'id', self::MORPH_BENEFICIARY_FOREIGN_KEY);
            });
        } elseif (! $this->foreignKeyExists('assistances', 'beneficiary_id', 'beneficiaries')) {
            Schema::table('assistances', function (Blueprint $table) {
                $table->foreign('beneficiary_id', self::MORPH_BENEFICIARY_FOREIGN_KEY)
                    ->references('id')
                    ->on('beneficiaries')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->foreignKeyExists('assistances', 'beneficiary_id', 'beneficiaries')) {
            Schema::table('assistances', function (Blueprint $table) {
                $table->dropForeign(self::MORPH_BENEFICIARY_FOREIGN_KEY);
            });
        }

        if (Schema::hasColumn('assistances', 'beneficiary_id')) {
            Schema::table('assistances', function (Blueprint $table) {
                $table->dropColumn('beneficiary_id');
            });
        }

        if ($this->foreignKeyExists('assistances', 'individual_id', 'individuals')) {
            Schema::table('assistances', function (Blueprint $table) {
                $table->dropForeign(['individual_id']);
            });
        }

        if (Schema::hasColumn('assistances', 'individual_id')) {
            Schema::table('assistances', function (Blueprint $table) {
                $table->renameColumn('individual_id', 'beneficiary_id');
            });
        }

        if (! $this->foreignKeyExists('assistances', 'beneficiary_id', 'individuals')) {
            Schema::table('assistances', function (Blueprint $table) {
                $table->foreign('beneficiary_id')
                    ->references('id')
                    ->on('individuals')
                    ->onDelete('cascade');
            });
        }
    }

    private function foreignKeyExists(string $table, string $column, string $referencedTable): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(
            static fn (array $foreignKey): bool => in_array($column, $foreignKey['columns'], true)
                && $foreignKey['foreign_table'] === $referencedTable,
        );
    }
};
