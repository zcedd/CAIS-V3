<?php

namespace App\Console\Commands\User;

use App\Models\Individual;
use App\Models\Organization;
use App\Services\User\BeneficiaryMorphService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('beneficiaries:reconcile-identity')]
#[Description('Sync beneficiaries.name and cais_number from individuals and organizations')]
class ReconcileBeneficiaryIdentityCommand extends Command
{
    public function handle(BeneficiaryMorphService $beneficiaryMorphService): int
    {
        $synced = 0;

        Individual::query()
            ->withTrashed()
            ->orderBy('id')
            ->chunkById(200, function ($individuals) use ($beneficiaryMorphService, &$synced): void {
                foreach ($individuals as $individual) {
                    $beneficiaryMorphService->syncMorphRecord(
                        $individual,
                        $individual->cais_number,
                        $individual->fullName(),
                    );
                    $synced++;
                }
            });

        Organization::query()
            ->withTrashed()
            ->orderBy('id')
            ->chunkById(200, function ($organizations) use ($beneficiaryMorphService, &$synced): void {
                foreach ($organizations as $organization) {
                    $beneficiaryMorphService->syncMorphRecord(
                        $organization,
                        $organization->cais_number,
                        $organization->name,
                    );
                    $synced++;
                }
            });

        $this->info("Reconciled {$synced} beneficiable records.");

        return self::SUCCESS;
    }
}
