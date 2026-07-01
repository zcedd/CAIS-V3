<?php

namespace App\Services\User;

use App\Models\Beneficiary;
use App\Models\Individual;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BeneficiaryMorphService
{
    /**
     * @param  Individual|Organization  $beneficiable
     */
    public function syncMorphRecord(Model $beneficiable, string $caisNumber, string $name): Beneficiary
    {
        /** @var Beneficiary $beneficiary */
        $beneficiary = $beneficiable->beneficiaryRecord()->updateOrCreate(
            [
                'beneficiable_type' => $beneficiable::class,
                'beneficiable_id' => $beneficiable->getKey(),
            ],
            [
                'cais_number' => $caisNumber,
                'name' => $name,
            ],
        );

        return $beneficiary;
    }

    public function createUniqueCaisNumber(string $prefix): string
    {
        $year = now()->format('Y');
        $normalizedPrefix = strtoupper($prefix);

        return DB::transaction(function () use ($normalizedPrefix, $year): string {
            $latestSequence = $this->resolveLatestCaisSequence($normalizedPrefix, $year);
            $nextSequence = $latestSequence + 1;

            do {
                $caisNumber = sprintf('%s-%s-%04d', $normalizedPrefix, $year, $nextSequence);
                $nextSequence++;
            } while ($this->caisNumberExists($caisNumber));

            return $caisNumber;
        });
    }

    private function resolveLatestCaisSequence(string $prefix, string $year): int
    {
        $pattern = "{$prefix}-{$year}-%";

        $numbers = collect([
            Individual::query()->where('cais_number', 'like', $pattern)->pluck('cais_number'),
            Organization::query()->where('cais_number', 'like', $pattern)->pluck('cais_number'),
            Beneficiary::query()->where('cais_number', 'like', $pattern)->pluck('cais_number'),
        ])->flatten();

        $maxSequence = $numbers
            ->map(static function (string $caisNumber) use ($prefix, $year): int {
                if (! preg_match('/^'.preg_quote($prefix, '/').'-'.preg_quote($year, '/').'-(\d+)$/', $caisNumber, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max();

        return $maxSequence ?? 0;
    }

    private function caisNumberExists(string $caisNumber): bool
    {
        return Individual::query()->where('cais_number', $caisNumber)->exists()
            || Organization::query()->where('cais_number', $caisNumber)->exists()
            || Beneficiary::query()->where('cais_number', $caisNumber)->exists();
    }
}
