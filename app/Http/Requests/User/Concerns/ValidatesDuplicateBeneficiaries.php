<?php

namespace App\Http\Requests\User\Concerns;

use App\Actions\User\FindPossibleDuplicateBeneficiaries;
use Illuminate\Validation\Validator;

trait ValidatesDuplicateBeneficiaries
{
    /**
     * @return array<string, mixed>
     */
    protected function duplicateAcknowledgementRules(): array
    {
        return [
            'duplicate_acknowledged' => ['nullable', 'boolean'],
        ];
    }

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
     */
    protected function afterDuplicateCheck(Validator $validator, array $input): void
    {
        $validator->after(function (Validator $validator) use ($input): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->boolean('duplicate_acknowledged')) {
                return;
            }

            $candidates = $this->container
                ->make(FindPossibleDuplicateBeneficiaries::class)($input);

            if ($candidates === []) {
                return;
            }

            session()->flash('duplicate_candidates', $candidates);

            $validator->errors()->add(
                'duplicates',
                'Possible existing records match this person.',
            );
        });
    }
}
