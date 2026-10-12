<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Legacy table names created before the distribution rename.
     *
     * @var array<string, string>
     */
    private array $tables = [
        'disbursement_approval_policies' => 'distribution_approval_policies',
        'disbursement_approval_stages' => 'distribution_approval_stages',
        'disbursement_batches' => 'distribution_batches',
        'disbursement_batch_items' => 'distribution_batch_items',
        'disbursement_batch_item_lines' => 'distribution_batch_item_lines',
        'disbursement_batch_revisions' => 'distribution_batch_revisions',
        'disbursement_approval_tasks' => 'distribution_approval_tasks',
        'disbursement_approval_decisions' => 'distribution_approval_decisions',
        'disbursement_release_records' => 'distribution_release_records',
        'disbursement_batch_documents' => 'distribution_batch_documents',
        'disbursement_batch_assignments' => 'distribution_batch_assignments',
        'disbursement_transactions' => 'distribution_transactions',
        'disbursement_evidence' => 'distribution_evidence',
        'disbursement_acknowledgment_receipts' => 'distribution_acknowledgment_receipts',
    ];

    public function up(): void
    {
        if (! $this->legacyTablesExist()) {
            $this->forgetPermissionCache();

            return;
        }

        $this->renameTables('disbursement', 'distribution');

        foreach ($this->tables as $table) {
            $this->renameMatchingColumns($table, 'disbursement', 'distribution');
            $this->renameMatchingIndexes($table, 'disbursement', 'distribution');
        }

        $this->replaceStoredText('Disbursement', 'Distribution');
        $this->replaceStoredText('disbursement', 'distribution');
        $this->moveStorageDirectory('disbursements', 'distributions');
        $this->moveStorageDirectory('disbursement-documents', 'distribution-documents');
        $this->dropLegacyMigrationRecord();
        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! $this->renamedAnExistingDatabase()) {
            return;
        }

        $this->replaceStoredText('Distribution', 'Disbursement');
        $this->replaceStoredText('distribution', 'disbursement');
        $this->moveStorageDirectory('distributions', 'disbursements');
        $this->moveStorageDirectory('distribution-documents', 'disbursement-documents');

        foreach ($this->tables as $table) {
            $this->renameMatchingIndexes($table, 'distribution', 'disbursement');
            $this->renameMatchingColumns($table, 'distribution', 'disbursement');
        }

        $this->renameTables('distribution', 'disbursement');
        $this->forgetPermissionCache();
    }

    private function legacyTablesExist(): bool
    {
        foreach (array_keys($this->tables) as $table) {
            if (Schema::hasTable($table)) {
                return true;
            }
        }

        return false;
    }

    private function renamedAnExistingDatabase(): bool
    {
        if (! Schema::hasTable('migrations') || ! Schema::hasTable('distribution_batches')) {
            return false;
        }

        $createBatch = DB::table('migrations')
            ->where('migration', '2026_10_11_142218_create_dispersal_module_tables')
            ->value('batch');

        $renameBatch = DB::table('migrations')
            ->where('migration', 'like', '%rename_disbursement_tables_to_distribution_tables')
            ->value('batch');

        return $createBatch !== null
            && $renameBatch !== null
            && (int) $createBatch !== (int) $renameBatch;
    }

    private function renameTables(string $fromToken, string $toToken): void
    {
        $pairs = [];

        foreach ($this->tables as $legacy => $renamed) {
            $from = $fromToken === 'disbursement' ? $legacy : $renamed;
            $to = $fromToken === 'disbursement' ? $renamed : $legacy;

            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                $pairs[] = sprintf(
                    '%s to %s',
                    DB::getQueryGrammar()->wrapTable($from),
                    DB::getQueryGrammar()->wrapTable($to),
                );
            }
        }

        if ($pairs === []) {
            return;
        }

        DB::statement('rename table '.implode(', ', $pairs));
    }

    private function renameMatchingColumns(string $table, string $fromToken, string $toToken): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach (Schema::getColumnListing($table) as $column) {
            if (! str_contains($column, $fromToken)) {
                continue;
            }

            $renamed = str_replace($fromToken, $toToken, $column);

            Schema::table($table, function (Blueprint $blueprint) use ($column, $renamed): void {
                $blueprint->renameColumn($column, $renamed);
            });
        }
    }

    private function renameMatchingIndexes(string $table, string $fromToken, string $toToken): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary'] || ! str_contains($index['name'], $fromToken)) {
                continue;
            }

            $renamed = str_replace($fromToken, $toToken, $index['name']);

            if ($renamed === $index['name'] || Schema::hasIndex($table, $renamed)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($index, $renamed): void {
                $blueprint->renameIndex($index['name'], $renamed);
            });
        }
    }

    private function replaceStoredText(string $from, string $to): void
    {
        $targets = [
            'distribution_evidence' => ['path', 'metadata'],
            'distribution_batch_documents' => ['path'],
            'distribution_acknowledgment_receipts' => ['snapshot'],
            'permissions' => ['name'],
            'activity_log' => ['log_name', 'description', 'subject_type', 'event', 'properties'],
            'notifications' => ['type', 'data'],
            'jobs' => ['payload'],
            'failed_jobs' => ['payload'],
        ];

        foreach ($targets as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->where($column, 'like', '%'.$from.'%')
                    ->update([
                        $column => DB::raw(sprintf(
                            'replace(%s, %s, %s)',
                            DB::getQueryGrammar()->wrap($column),
                            DB::getPdo()->quote($from),
                            DB::getPdo()->quote($to),
                        )),
                    ]);
            }
        }
    }

    private function moveStorageDirectory(string $from, string $to): void
    {
        $source = storage_path('app/private/'.$from);
        $destination = storage_path('app/private/'.$to);

        if (! is_dir($source) || is_dir($destination)) {
            return;
        }

        File::moveDirectory($source, $destination);
    }

    private function dropLegacyMigrationRecord(): void
    {
        if (! Schema::hasTable('migrations')) {
            return;
        }

        DB::table('migrations')
            ->where('migration', '2026_10_11_175016_add_disbursement_venue_release_tables')
            ->delete();
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
