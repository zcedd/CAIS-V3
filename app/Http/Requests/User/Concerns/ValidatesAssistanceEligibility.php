<?php

namespace App\Http\Requests\User\Concerns;

use App\Actions\User\GuardAssistanceEligibility;
use App\Models\Assistance;
use App\Models\Beneficiary;
use App\Models\Program;
use DateTimeInterface;
use Illuminate\Validation\Validator;

trait ValidatesAssistanceEligibility
{
    /**
     * @return array<string, mixed>
     */
    protected function eligibilityOverrideRules(): array
    {
        return [
            'eligibility_override_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function eligibilityOverrideAttributes(): array
    {
        return [
            'eligibility_override_reason' => 'eligibility override reason',
        ];
    }

    protected function afterAssistanceEligibility(
        Validator $validator,
        Program $program,
        ?int $exceptAssistanceId = null,
        ?DateTimeInterface $asOf = null,
    ): void {
        $validator->after(function (Validator $validator) use ($program, $exceptAssistanceId, $asOf): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $beneficiary = Beneficiary::query()->find($this->integer('beneficiary_id'));

            if (! $beneficiary instanceof Beneficiary) {
                return;
            }

            $itemDetails = collect($this->input('item_details', []))
                ->filter(static fn (mixed $row): bool => is_array($row))
                ->map(static fn (array $row): array => [
                    'item_id' => (int) ($row['item_id'] ?? 0),
                    'quantity' => (int) ($row['quantity'] ?? 0),
                ])
                ->filter(static fn (array $row): bool => $row['item_id'] > 0 && $row['quantity'] > 0)
                ->values()
                ->all();

            $this->container->make(GuardAssistanceEligibility::class)->applyToValidator(
                $validator,
                $program,
                $beneficiary,
                $itemDetails,
                $this->input('eligibility_override_reason'),
                $asOf,
                $exceptAssistanceId,
            );
        });
    }

    protected function applyTransferEligibility(
        Validator $validator,
        Program $targetProgram,
        Assistance $assistance,
    ): void {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $beneficiary = $assistance->beneficiary;

        if (! $beneficiary instanceof Beneficiary) {
            return;
        }

        $itemDetails = $assistance->assistanceItem
            ->map(static fn ($item): array => [
                'item_id' => (int) $item->item_id,
                'quantity' => (int) $item->quantity,
            ])
            ->all();

        $this->container->make(GuardAssistanceEligibility::class)->applyToValidator(
            $validator,
            $targetProgram,
            $beneficiary,
            $itemDetails,
            $this->input('eligibility_override_reason'),
            now(),
            $assistance->id,
        );
    }
}
