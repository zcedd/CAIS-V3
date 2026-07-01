<?php

namespace App\Services\User;

use App\Models\Beneficiary;
use App\Models\Individual;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class IndividualBeneficiaryService
{
    public function __construct(
        private BeneficiaryMorphService $beneficiaryMorphService,
    ) {}

    /**
     * @param  array{
     *     first_name: string,
     *     middle_name?: string|null,
     *     last_name: string,
     *     suffix?: string|null,
     *     birthday?: string|null,
     *     sex: string,
     *     other_address?: string|null,
     *     civil_status_id?: int|null,
     *     mobile_number?: string|null,
     *     indigenous?: bool|null,
     *     ethnicity?: string|null,
     *     pwd?: bool|null,
     *     is_4ps_beneficiary?: bool|null,
     *     is_solo_parent?: bool|null,
     *     spouse?: string|null,
     *     address_barangay_id?: int|null,
     *     identifications?: list<array{identification_id: int, number: string}>
     * }  $validated
     */
    public function create(array $validated): Individual
    {
        return DB::transaction(function () use ($validated): Individual {
            $caisNumber = $this->beneficiaryMorphService->createUniqueCaisNumber('IND');

            $individual = Individual::query()->create([
                'cais_number' => $caisNumber,
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'suffix' => $validated['suffix'] ?? null,
                'birthday' => $validated['birthday'] ?? null,
                'sex' => $validated['sex'],
                'other_address' => $validated['other_address'] ?? null,
                'civil_status_id' => $validated['civil_status_id'] ?? null,
                'mobile_number' => $validated['mobile_number'] ?? null,
                'indigenous' => $validated['indigenous'] ?? false,
                'ethnicity' => $validated['ethnicity'] ?? null,
                'pwd' => $validated['pwd'] ?? false,
                'is_4ps_beneficiary' => $validated['is_4ps_beneficiary'] ?? false,
                'is_solo_parent' => $validated['is_solo_parent'] ?? false,
                'spouse' => $validated['spouse'] ?? null,
                'address_barangay_id' => $validated['address_barangay_id'] ?? null,
            ]);

            $this->syncIdentifications($individual, $validated['identifications'] ?? []);

            $this->beneficiaryMorphService->syncMorphRecord(
                $individual,
                $caisNumber,
                $individual->fullName(),
            );

            return $individual->refresh();
        });
    }

    /**
     * @param  array{
     *     first_name: string,
     *     middle_name?: string|null,
     *     last_name: string,
     *     suffix?: string|null,
     *     birthday?: string|null,
     *     sex: string,
     *     other_address?: string|null,
     *     civil_status_id?: int|null,
     *     mobile_number?: string|null,
     *     indigenous?: bool|null,
     *     ethnicity?: string|null,
     *     pwd?: bool|null,
     *     is_4ps_beneficiary?: bool|null,
     *     is_solo_parent?: bool|null,
     *     spouse?: string|null,
     *     address_barangay_id?: int|null,
     *     identifications?: list<array{identification_id: int, number: string}>
     * }  $validated
     */
    public function update(Beneficiary $beneficiary, array $validated): Individual
    {
        return DB::transaction(function () use ($beneficiary, $validated): Individual {
            /** @var Individual $individual */
            $individual = $beneficiary->beneficiable;

            $individual->update([
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'suffix' => $validated['suffix'] ?? null,
                'birthday' => $validated['birthday'] ?? null,
                'sex' => $validated['sex'],
                'other_address' => $validated['other_address'] ?? null,
                'civil_status_id' => $validated['civil_status_id'] ?? null,
                'mobile_number' => $validated['mobile_number'] ?? null,
                'indigenous' => $validated['indigenous'] ?? false,
                'ethnicity' => $validated['ethnicity'] ?? null,
                'pwd' => $validated['pwd'] ?? false,
                'is_4ps_beneficiary' => $validated['is_4ps_beneficiary'] ?? false,
                'is_solo_parent' => $validated['is_solo_parent'] ?? false,
                'spouse' => $validated['spouse'] ?? null,
                'address_barangay_id' => array_key_exists('address_barangay_id', $validated)
                    ? $validated['address_barangay_id']
                    : $individual->address_barangay_id,
            ]);

            $this->syncIdentifications($individual, $validated['identifications'] ?? []);

            $this->beneficiaryMorphService->syncMorphRecord(
                $individual->refresh(),
                $individual->cais_number,
                $individual->fullName(),
            );

            return $individual->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function editPayload(Beneficiary $beneficiary): array
    {
        /** @var Individual $individual */
        $individual = $beneficiary->beneficiable;
        $individual->load([
            'identification:id,name',
            'address.city:id,name',
            'civilStatus:id,name',
        ]);

        return [
            'type' => 'individual',
            'beneficiary_id' => $beneficiary->id,
            'individual' => [
                'first_name' => $individual->first_name,
                'middle_name' => $individual->middle_name,
                'last_name' => $individual->last_name,
                'suffix' => $individual->suffix,
                'birthday' => $individual->birthday
                    ? Carbon::parse($individual->birthday)->toDateString()
                    : null,
                'sex' => $individual->sex,
                'other_address' => $individual->other_address,
                'civil_status_id' => $individual->civil_status_id,
                'mobile_number' => $individual->mobile_number,
                'indigenous' => (bool) $individual->indigenous,
                'ethnicity' => $individual->ethnicity,
                'pwd' => (bool) $individual->pwd,
                'is_4ps_beneficiary' => (bool) $individual->is_4ps_beneficiary,
                'is_solo_parent' => (bool) $individual->is_solo_parent,
                'spouse' => $individual->spouse,
                'address_barangay_id' => $individual->address_barangay_id,
                'identifications' => $individual->identification
                    ->map(static fn ($identification): array => [
                        'identification_id' => $identification->id,
                        'number' => $identification->pivot->number,
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showDetails(Beneficiary $beneficiary): array
    {
        /** @var Individual $individual */
        $individual = $beneficiary->beneficiable;
        $individual->load([
            'identification:id,name',
            'address.city:id,name',
            'civilStatus:id,name',
            'organization:id,name,cais_number',
        ]);

        return [
            'first_name' => $individual->first_name,
            'middle_name' => $individual->middle_name,
            'last_name' => $individual->last_name,
            'suffix' => $individual->suffix,
            'birthday' => $individual->birthday
                ? Carbon::parse($individual->birthday)->toDateString()
                : null,
            'sex' => $individual->sex,
            'mobile_number' => $individual->mobile_number,
            'other_address' => $individual->other_address,
            'civil_status' => $individual->civilStatus?->name,
            'address' => $individual->address
                ? ($individual->address->city
                    ? "{$individual->address->name}, {$individual->address->city->name}"
                    : $individual->address->name)
                : null,
            'indigenous' => (bool) $individual->indigenous,
            'ethnicity' => $individual->ethnicity,
            'pwd' => (bool) $individual->pwd,
            'is_4ps_beneficiary' => (bool) $individual->is_4ps_beneficiary,
            'is_solo_parent' => (bool) $individual->is_solo_parent,
            'spouse' => $individual->spouse,
            'identifications' => $individual->identification
                ->map(static fn ($identification): array => [
                    'name' => $identification->name,
                    'number' => $identification->pivot->number,
                ])
                ->values()
                ->all(),
            'organizations' => $individual->organization
                ->map(static fn (Organization $organization): array => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'cais_number' => $organization->cais_number,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<array{identification_id: int, number: string}>  $identifications
     */
    private function syncIdentifications(Individual $individual, array $identifications): void
    {
        $syncData = [];

        foreach ($identifications as $identification) {
            $syncData[$identification['identification_id']] = [
                'number' => $identification['number'],
            ];
        }

        $individual->identification()->sync($syncData);
    }
}
