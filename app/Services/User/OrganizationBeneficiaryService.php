<?php

namespace App\Services\User;

use App\Models\Beneficiary;
use App\Models\Individual;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class OrganizationBeneficiaryService
{
    public function __construct(
        private BeneficiaryMorphService $beneficiaryMorphService,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     beneficiary_id: int,
     *     addrs_brgy_id?: int|null,
     *     mobile_number?: string|null,
     *     total_member?: int|null,
     *     member_ids?: list<int>
     * }  $validated
     */
    public function create(array $validated): Organization
    {
        return DB::transaction(function () use ($validated): Organization {
            $caisNumber = $this->beneficiaryMorphService->createUniqueCaisNumber('ORG');

            $organization = Organization::query()->create([
                'cais_number' => $caisNumber,
                'name' => $validated['name'],
                'beneficiary_id' => $validated['beneficiary_id'],
                'addrs_brgy_id' => $validated['addrs_brgy_id'] ?? null,
                'mobile_number' => $validated['mobile_number'] ?? null,
                'total_member' => $validated['total_member'] ?? count($validated['member_ids'] ?? []),
            ]);

            $this->syncMembers($organization, $validated);

            $this->beneficiaryMorphService->syncMorphRecord(
                $organization,
                $caisNumber,
                $organization->name,
            );

            return $organization->refresh();
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     beneficiary_id: int,
     *     addrs_brgy_id?: int|null,
     *     mobile_number?: string|null,
     *     total_member?: int|null,
     *     member_ids?: list<int>
     * }  $validated
     */
    public function update(Beneficiary $beneficiary, array $validated): Organization
    {
        return DB::transaction(function () use ($beneficiary, $validated): Organization {
            /** @var Organization $organization */
            $organization = $beneficiary->beneficiable;

            $organization->update([
                'name' => $validated['name'],
                'beneficiary_id' => $validated['beneficiary_id'],
                'addrs_brgy_id' => $validated['addrs_brgy_id'] ?? null,
                'mobile_number' => $validated['mobile_number'] ?? null,
                'total_member' => $validated['total_member'] ?? count($validated['member_ids'] ?? []),
            ]);

            $this->syncMembers($organization, $validated);

            $this->beneficiaryMorphService->syncMorphRecord(
                $organization->refresh(),
                $organization->cais_number,
                $organization->name,
            );

            return $organization->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function editPayload(Beneficiary $beneficiary): array
    {
        /** @var Organization $organization */
        $organization = $beneficiary->beneficiable;
        $organization->load([
            'president:id,first_name,middle_name,last_name,suffix,cais_number',
            'beneficiary:id,first_name,middle_name,last_name,suffix,cais_number',
            'address.city:id,name',
        ]);

        return [
            'type' => 'organization',
            'beneficiary_id' => $beneficiary->id,
            'organization' => [
                'name' => $organization->name,
                'beneficiary_id' => $organization->beneficiary_id,
                'president' => $organization->president ? [
                    'id' => $organization->president->id,
                    'name' => $organization->president->fullName(),
                    'cais_number' => $organization->president->cais_number,
                ] : null,
                'addrs_brgy_id' => $organization->addrs_brgy_id,
                'mobile_number' => $organization->mobile_number,
                'total_member' => $organization->total_member,
                'members' => $organization->beneficiary
                    ->map(static fn (Individual $member): array => [
                        'id' => $member->id,
                        'name' => $member->fullName(),
                        'cais_number' => $member->cais_number,
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
        /** @var Organization $organization */
        $organization = $beneficiary->beneficiable;
        $organization->load([
            'president:id,first_name,middle_name,last_name,suffix,cais_number',
            'beneficiary:id,first_name,middle_name,last_name,suffix,cais_number',
            'address.city:id,name',
        ]);

        return [
            'mobile_number' => $organization->mobile_number,
            'total_member' => $organization->total_member,
            'address' => $organization->address
                ? ($organization->address->city
                    ? "{$organization->address->name}, {$organization->address->city->name}"
                    : $organization->address->name)
                : null,
            'president' => $organization->president ? [
                'id' => $organization->president->id,
                'name' => $organization->president->fullName(),
                'cais_number' => $organization->president->cais_number,
            ] : null,
            'members' => $organization->beneficiary
                ->map(static fn (Individual $member): array => [
                    'id' => $member->id,
                    'name' => $member->fullName(),
                    'cais_number' => $member->cais_number,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{
     *     beneficiary_id: int,
     *     member_ids?: list<int>
     * }  $validated
     */
    private function syncMembers(Organization $organization, array $validated): void
    {
        $memberIds = collect($validated['member_ids'] ?? [])
            ->push($validated['beneficiary_id'])
            ->unique()
            ->values()
            ->all();

        $organization->beneficiary()->sync($memberIds);
    }
}
