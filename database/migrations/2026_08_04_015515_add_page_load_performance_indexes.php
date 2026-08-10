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
        if (! $this->hasIndexCovering('assistances', ['beneficiary_id'])) {
            Schema::table('assistances', function (Blueprint $table): void {
                $table->index('beneficiary_id', 'assistances_beneficiary_id_index');
            });
        }

        if (! $this->hasIndexCovering('assistance_item', ['assistance_id', 'is_received', 'deleted_at'])) {
            Schema::table('assistance_item', function (Blueprint $table): void {
                $table->index(
                    ['assistance_id', 'is_received', 'deleted_at'],
                    'assistance_item_assistance_received_deleted_index',
                );
            });
        }

        if (! $this->hasIndexCovering('notifications', ['notifiable_type', 'notifiable_id', 'read_at'])) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->index(
                    ['notifiable_type', 'notifiable_id', 'read_at'],
                    'notifications_notifiable_unread_index',
                );
            });
        }

        if (! $this->hasIndexCovering('programs', ['department_id', 'is_closed'])) {
            Schema::table('programs', function (Blueprint $table): void {
                $table->index(
                    ['department_id', 'is_closed'],
                    'programs_department_id_is_closed_index',
                );
            });
        }

        if (! $this->hasIndexCovering('beneficiaries', ['name'])) {
            Schema::table('beneficiaries', function (Blueprint $table): void {
                $table->index('name', 'beneficiaries_name_index');
            });
        }

        if (
            Schema::hasTable('address_barangays')
            && ! $this->hasIndexCovering('address_barangays', ['address_city_id'])
        ) {
            Schema::table('address_barangays', function (Blueprint $table): void {
                $table->index('address_city_id', 'address_barangays_address_city_id_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('assistances', 'assistances_beneficiary_id_index');
        $this->dropIndexIfExists('assistance_item', 'assistance_item_assistance_received_deleted_index');
        $this->dropIndexIfExists('notifications', 'notifications_notifiable_unread_index');
        $this->dropIndexIfExists('programs', 'programs_department_id_is_closed_index');
        $this->dropIndexIfExists('beneficiaries', 'beneficiaries_name_index');

        if (Schema::hasTable('address_barangays')) {
            $this->dropIndexIfExists('address_barangays', 'address_barangays_address_city_id_index');
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasIndexCovering(string $table, array $columns): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            function (array $definition) use ($columns): bool {
                $indexColumns = $definition['columns'] ?? [];

                return array_slice($indexColumns, 0, count($columns)) === $columns;
            },
        );
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = collect(Schema::getIndexes($table))
            ->contains(fn (array $definition): bool => ($definition['name'] ?? null) === $index);

        if (! $exists) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index): void {
            $blueprint->dropIndex($index);
        });
    }
};
