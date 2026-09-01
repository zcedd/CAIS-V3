<?php

namespace App\Actions\User;

use App\Models\Beneficiary;
use App\Models\Individual;
use App\Models\IndividualIdentification;
use App\Models\Organization;
use App\Support\IdentityNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FindPossibleDuplicateBeneficiaries
{
    private const CANDIDATE_LIMIT = 25;

    /**
     * @param  array{
     *     type: 'individual'|'organization',
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     birthday?: string|null,
     *     address_barangay_id?: int|null,
     *     identifications?: list<array{identification_id?: int, number?: string}>,
     *     name?: string|null,
     *     exclude_beneficiary_id?: int|null
     * }  $input
     * @return list<array{
     *     id: int,
     *     cais_number: string|null,
     *     name: string,
     *     birthday: string|null,
     *     barangay: string|null,
     *     score: 'high'|'medium'|'low',
     *     match_reasons: list<string>
     * }>
     */
    public function __invoke(array $input): array
    {
        $type = $input['type'];

        $matches = $type === 'organization'
            ? $this->matchOrganizations($input)
            : $this->matchIndividuals($input);

        return $matches
            ->sortBy(static function (array $match): int {
                return match ($match['score']) {
                    'high' => 0,
                    'medium' => 1,
                    default => 2,
                };
            })
            ->take(self::CANDIDATE_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, array{
     *     id: int,
     *     cais_number: string|null,
     *     name: string,
     *     birthday: string|null,
     *     barangay: string|null,
     *     score: 'high'|'medium'|'low',
     *     match_reasons: list<string>
     * }>
     */
    private function matchIndividuals(array $input): Collection
    {
        $firstName = IdentityNormalizer::name($input['first_name'] ?? null);
        $lastName = IdentityNormalizer::name($input['last_name'] ?? null);
        $birthday = $this->normalizeDate($input['birthday'] ?? null);
        $barangayId = isset($input['address_barangay_id']) ? (int) $input['address_barangay_id'] : 0;
        $barangayId = $barangayId > 0 ? $barangayId : null;
        $identificationNumbers = $this->normalizedIdentificationNumbers($input['identifications'] ?? []);
        $excludeBeneficiaryId = isset($input['exclude_beneficiary_id'])
            ? (int) $input['exclude_beneficiary_id']
            : null;

        if ($lastName === '' && $identificationNumbers === []) {
            return collect();
        }

        $individualIds = collect();

        if ($identificationNumbers !== []) {
            $individualIds = $individualIds->merge(
                $this->individualIdsMatchingIdentificationNumbers($identificationNumbers),
            );
        }

        if ($lastName !== '') {
            $individualIds = $individualIds->merge(
                Individual::query()
                    ->where(function (Builder $query) use ($lastName, $firstName, $birthday): void {
                        $query->whereRaw('UPPER(TRIM(last_name)) = ?', [$lastName]);

                        $query->orWhereRaw('SOUNDEX(last_name) = SOUNDEX(?)', [$lastName]);

                        if ($firstName !== '') {
                            $query->orWhere(function (Builder $nameQuery) use ($lastName, $firstName): void {
                                $nameQuery
                                    ->whereRaw('UPPER(TRIM(last_name)) = ?', [$lastName])
                                    ->whereRaw('UPPER(TRIM(first_name)) = ?', [$firstName]);
                            });
                        }

                        if ($birthday !== null) {
                            $query->orWhere(function (Builder $birthdayQuery) use ($lastName, $birthday): void {
                                $birthdayQuery
                                    ->whereRaw('UPPER(TRIM(last_name)) = ?', [$lastName])
                                    ->whereDate('birthday', $birthday);
                            });
                        }
                    })
                    ->limit(50)
                    ->pluck('id'),
            );
        }

        $individualIds = $individualIds->unique()->values();

        if ($individualIds->isEmpty()) {
            return collect();
        }

        $individuals = Individual::query()
            ->with(['beneficiaryRecord:id,cais_number,name,beneficiable_type,beneficiable_id', 'address:id,name', 'beneficiaryIdentification'])
            ->whereIn('id', $individualIds->all())
            ->get();

        return $individuals
            ->map(function (Individual $individual) use (
                $firstName,
                $lastName,
                $birthday,
                $barangayId,
                $identificationNumbers,
                $excludeBeneficiaryId,
            ): ?array {
                $beneficiary = $individual->beneficiaryRecord;

                if (! $beneficiary instanceof Beneficiary) {
                    return null;
                }

                if ($excludeBeneficiaryId !== null && $beneficiary->id === $excludeBeneficiaryId) {
                    return null;
                }

                $reasons = [];
                $scoreRank = 3;

                $candidateFirst = IdentityNormalizer::name($individual->first_name);
                $candidateLast = IdentityNormalizer::name($individual->last_name);
                $candidateBirthday = $this->normalizeDate(
                    $individual->birthday !== null ? (string) $individual->birthday : null,
                );
                $sameLast = $lastName !== '' && $candidateLast === $lastName;
                $sameFirst = $firstName !== '' && $candidateFirst === $firstName;
                $sameBirthday = $birthday !== null && $candidateBirthday === $birthday;
                $sameBarangay = $barangayId !== null && (int) $individual->address_barangay_id === $barangayId;

                $matchedId = $individual->beneficiaryIdentification
                    ->contains(function (IndividualIdentification $identification) use ($identificationNumbers): bool {
                        $normalized = IdentityNormalizer::identificationNumber($identification->number);

                        return $normalized !== '' && in_array($normalized, $identificationNumbers, true);
                    });

                if ($matchedId) {
                    $reasons[] = 'Same ID number';
                    $scoreRank = 0;
                }

                if ($sameLast && $sameFirst && $sameBirthday) {
                    $reasons[] = 'Same name and birthday';
                    $scoreRank = 0;
                }

                if ($scoreRank > 0 && $sameLast && $sameBirthday && $sameBarangay) {
                    $reasons[] = 'Same last name, birthday, and barangay';
                    $scoreRank = min($scoreRank, 1);
                }

                if (
                    $scoreRank > 0
                    && $sameBirthday
                    && $lastName !== ''
                    && $firstName !== ''
                    && $this->soundexEquals($candidateLast, $lastName)
                    && $this->soundexEquals($candidateFirst, $firstName)
                ) {
                    $reasons[] = 'Similar name and same birthday';
                    $scoreRank = min($scoreRank, 1);
                }

                if ($scoreRank > 1 && $sameLast && $sameFirst && $sameBarangay && ! $sameBirthday) {
                    $reasons[] = 'Same name and barangay';
                    $scoreRank = 2;
                }

                if ($reasons === []) {
                    return null;
                }

                return [
                    'id' => $beneficiary->id,
                    'cais_number' => $beneficiary->cais_number,
                    'name' => $beneficiary->name,
                    'birthday' => $candidateBirthday,
                    'barangay' => $individual->address?->name,
                    'score' => match ($scoreRank) {
                        0 => 'high',
                        1 => 'medium',
                        default => 'low',
                    },
                    'match_reasons' => array_values(array_unique($reasons)),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, array{
     *     id: int,
     *     cais_number: string|null,
     *     name: string,
     *     birthday: string|null,
     *     barangay: string|null,
     *     score: 'high'|'medium'|'low',
     *     match_reasons: list<string>
     * }>
     */
    private function matchOrganizations(array $input): Collection
    {
        $name = IdentityNormalizer::name($input['name'] ?? null);
        $barangayId = isset($input['address_barangay_id']) ? (int) $input['address_barangay_id'] : 0;
        $barangayId = $barangayId > 0 ? $barangayId : null;
        $excludeBeneficiaryId = isset($input['exclude_beneficiary_id'])
            ? (int) $input['exclude_beneficiary_id']
            : null;

        if ($name === '') {
            return collect();
        }

        $organizations = Organization::query()
            ->with(['beneficiaryRecord:id,cais_number,name,beneficiable_type,beneficiable_id', 'address:id,name'])
            ->where(function (Builder $query) use ($name): void {
                $query
                    ->whereRaw('UPPER(TRIM(name)) = ?', [$name])
                    ->orWhereRaw('SOUNDEX(name) = SOUNDEX(?)', [$name]);
            })
            ->limit(50)
            ->get();

        return $organizations
            ->map(function (Organization $organization) use ($name, $barangayId, $excludeBeneficiaryId): ?array {
                $beneficiary = $organization->beneficiaryRecord;

                if (! $beneficiary instanceof Beneficiary) {
                    return null;
                }

                if ($excludeBeneficiaryId !== null && $beneficiary->id === $excludeBeneficiaryId) {
                    return null;
                }

                $candidateName = IdentityNormalizer::name($organization->name);
                $sameName = $candidateName === $name;
                $sameBarangay = $barangayId !== null && (int) $organization->address_barangay_id === $barangayId;
                $similarName = $this->soundexEquals($candidateName, $name);

                $reasons = [];
                $score = null;

                if ($sameName && ($sameBarangay || $barangayId === null)) {
                    $reasons[] = $sameBarangay ? 'Same organization name and barangay' : 'Same organization name';
                    $score = 'high';
                } elseif ($sameName) {
                    $reasons[] = 'Same organization name';
                    $score = 'medium';
                } elseif ($similarName && $sameBarangay) {
                    $reasons[] = 'Similar organization name and same barangay';
                    $score = 'medium';
                }

                if ($score === null) {
                    return null;
                }

                return [
                    'id' => $beneficiary->id,
                    'cais_number' => $beneficiary->cais_number,
                    'name' => $beneficiary->name,
                    'birthday' => null,
                    'barangay' => $organization->address?->name,
                    'score' => $score,
                    'match_reasons' => $reasons,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  list<array{identification_id?: int, number?: string}>  $identifications
     * @return list<string>
     */
    private function normalizedIdentificationNumbers(array $identifications): array
    {
        return collect($identifications)
            ->map(static fn (array $row): string => IdentityNormalizer::identificationNumber($row['number'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $normalizedNumbers
     * @return Collection<int, int>
     */
    private function individualIdsMatchingIdentificationNumbers(array $normalizedNumbers): Collection
    {
        return IndividualIdentification::query()
            ->where(function (Builder $query) use ($normalizedNumbers): void {
                foreach ($normalizedNumbers as $number) {
                    $query->orWhereRaw(
                        "UPPER(REPLACE(REPLACE(REPLACE(number, ' ', ''), '-', ''), '.', '')) = ?",
                        [$number],
                    );
                }
            })
            ->pluck('beneficiary_id');
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function soundexEquals(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        return soundex($left) === soundex($right);
    }
}
