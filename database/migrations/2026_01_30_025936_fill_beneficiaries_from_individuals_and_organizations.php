<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $individualToBeneficiary = [];
        $organizationToBeneficiary = [];

        DB::table('individuals')
            ->orderBy('id')
            ->get()
            ->each(function (object $individual) use ($now, &$individualToBeneficiary): void {
                $displayName = trim(implode(' ', array_filter([
                    $individual->first_name ?? '',
                    $individual->last_name ?? '',
                    $individual->suffix ?? '',
                ]))) ?: 'Unknown';

                $beneficiaryId = DB::table('beneficiaries')->insertGetId([
                    'cais_number' => $individual->cais_number,
                    'type' => 'individual',
                    'display_name' => $displayName,
                    'created_at' => $individual->created_at ?? $now,
                    'updated_at' => $individual->updated_at ?? $now,
                ]);
                $individualToBeneficiary[(int) $individual->id] = $beneficiaryId;
            });

        DB::table('organizations')
            ->orderBy('id')
            ->get()
            ->each(function (object $organization) use ($now, &$organizationToBeneficiary): void {
                $beneficiaryId = DB::table('beneficiaries')->insertGetId([
                    'cais_number' => $organization->cais_number,
                    'type' => 'organization',
                    'display_name' => $organization->name ?? 'Unknown',
                    'created_at' => $organization->created_at ?? $now,
                    'updated_at' => $organization->updated_at ?? $now,
                ]);
                $organizationToBeneficiary[(int) $organization->id] = $beneficiaryId;
            });

        foreach ($individualToBeneficiary as $individualId => $beneficiaryId) {
            DB::table('assistance_requests')
                ->where('individual_id', $individualId)
                ->update(['beneficiary_id' => $beneficiaryId]);
        }

        foreach ($organizationToBeneficiary as $organizationId => $beneficiaryId) {
            DB::table('assistance_requests')
                ->where('organization_id', $organizationId)
                ->update(['beneficiary_id' => $beneficiaryId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $beneficiaryIds = DB::table('beneficiaries')
            ->whereIn('type', ['individual', 'organization'])
            ->pluck('id');

        if ($beneficiaryIds->isNotEmpty()) {
            DB::table('assistance_requests')
                ->whereIn('beneficiary_id', $beneficiaryIds)
                ->update(['beneficiary_id' => null]);
        }

        DB::table('beneficiaries')->whereIn('type', ['individual', 'organization'])->delete();
    }
};
