<?php

use App\Models\Individual;
use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->unique(['beneficiable_type', 'beneficiable_id'], 'beneficiaries_beneficiable_unique');
        });

        $this->populateBeneficiariesFrom(Individual::class, 'individuals');
        $this->populateBeneficiariesFrom(Organization::class, 'organizations');

        $this->linkAssistancesToBeneficiaries(Individual::class, 'individual_id');
        $this->linkAssistancesToBeneficiaries(Organization::class, 'organization_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('assistances')->update(['beneficiary_id' => null]);

        DB::table('beneficiaries')->truncate();

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropUnique('beneficiaries_beneficiable_unique');
        });
    }

    private function populateBeneficiariesFrom(string $morphType, string $sourceTable): void
    {
        DB::table($sourceTable)
            ->orderBy('id')
            ->chunkById(1000, function ($records) use ($morphType): void {
                $existingIds = DB::table('beneficiaries')
                    ->where('beneficiable_type', $morphType)
                    ->whereIn('beneficiable_id', $records->pluck('id'))
                    ->pluck('beneficiable_id')
                    ->all();

                $rows = [];

                foreach ($records as $record) {
                    if (in_array($record->id, $existingIds, true)) {
                        continue;
                    }

                    $rows[] = [
                        'cais_number' => $record->cais_number,
                        'beneficiable_type' => $morphType,
                        'beneficiable_id' => $record->id,
                        'created_at' => $record->created_at,
                        'updated_at' => $record->updated_at,
                        'deleted_at' => $record->deleted_at,
                    ];
                }

                if ($rows !== []) {
                    DB::table('beneficiaries')->insert($rows);
                }
            });
    }

    private function linkAssistancesToBeneficiaries(string $morphType, string $sourceColumn): void
    {
        DB::table('assistances')
            ->join('beneficiaries', function ($join) use ($morphType, $sourceColumn): void {
                $join->on('beneficiaries.beneficiable_id', '=', "assistances.{$sourceColumn}")
                    ->where('beneficiaries.beneficiable_type', '=', $morphType);
            })
            ->whereNotNull("assistances.{$sourceColumn}")
            ->whereNull('assistances.beneficiary_id')
            ->update([
                'assistances.beneficiary_id' => DB::raw('beneficiaries.id'),
            ]);
    }
};
