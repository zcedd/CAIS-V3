<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\SearchBeneficiariesRequest;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Individual;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class BeneficiaryController extends Controller
{
    private const SEARCH_LIMIT = 15;

    /**
     * Search beneficiaries by CAIS number or name for autocomplete.
     */
    public function search(
        SearchBeneficiariesRequest $request,
    ): JsonResponse {
        $search = $request->search();
        $beneficiaryType = $request->beneficiaryType();

        $query = Beneficiary::query()
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT);

        if ($beneficiaryType === 'individual') {
            $query->where('beneficiable_type', Individual::class);
        }

        if ($beneficiaryType === 'organization') {
            $query->where('beneficiable_type', Organization::class);
        }

        if ($search !== '') {
            $needle = '%' . $search . '%';

            $query->where(function ($builder) use ($needle): void {
                $builder
                    ->where('name', 'like', $needle)
                    ->orWhere('cais_number', 'like', $needle);
            });
        }

        $beneficiaries = $query
            ->get(['id', 'cais_number', 'name'])
            ->map(static fn(Beneficiary $beneficiary): array => [
                'id' => $beneficiary->id,
                'organization_id' => $beneficiary->beneficiable_type === Organization::class
                    ? $beneficiary->beneficiable_id
                    : null,
                'cais_number' => $beneficiary->cais_number,
                'name' => $beneficiary->name,
                'label' => trim("{$beneficiary->cais_number} - {$beneficiary->name}"),
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => $beneficiaries,
        ]);
    }
}
