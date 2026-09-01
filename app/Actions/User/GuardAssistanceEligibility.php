<?php

namespace App\Actions\User;

use App\Models\Beneficiary;
use App\Models\Program;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class GuardAssistanceEligibility
{
    public function __construct(
        private EvaluateAssistanceEligibility $evaluateAssistanceEligibility,
    ) {}

    /**
     * @param  list<array{item_id: int, quantity: int}>  $itemDetails
     * @return list<array{severity: 'hard'|'soft', code: string, message: string, assistance_id?: int, item_id?: int}>
     */
    public function assert(
        Program $program,
        Beneficiary $beneficiary,
        array $itemDetails = [],
        ?string $overrideReason = null,
        ?DateTimeInterface $asOf = null,
        ?int $exceptAssistanceId = null,
    ): array {
        $asOf = $asOf instanceof Carbon ? $asOf : Carbon::parse($asOf ?? now());
        $findings = ($this->evaluateAssistanceEligibility)(
            $program,
            $beneficiary,
            $itemDetails,
            $asOf,
            $exceptAssistanceId,
        );

        $hardMessages = collect($findings)
            ->where('severity', 'hard')
            ->pluck('message')
            ->values()
            ->all();

        if ($hardMessages !== []) {
            session()->flash('eligibility_findings', $findings);

            throw ValidationException::withMessages([
                'beneficiary_id' => $hardMessages,
            ]);
        }

        $hasSoftFindings = collect($findings)->contains(
            static fn (array $finding): bool => $finding['severity'] === 'soft',
        );

        if ($hasSoftFindings && blank($overrideReason)) {
            session()->flash('eligibility_findings', $findings);

            throw ValidationException::withMessages([
                'eligibility_override_reason' => 'A reason is required to proceed past the eligibility warning.',
            ]);
        }

        return $findings;
    }

    /**
     * @param  list<array{item_id: int, quantity: int}>  $itemDetails
     */
    public function applyToValidator(
        Validator $validator,
        Program $program,
        Beneficiary $beneficiary,
        array $itemDetails = [],
        ?string $overrideReason = null,
        ?DateTimeInterface $asOf = null,
        ?int $exceptAssistanceId = null,
    ): void {
        try {
            $this->assert(
                $program,
                $beneficiary,
                $itemDetails,
                $overrideReason,
                $asOf,
                $exceptAssistanceId,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($key, $message);
                }
            }
        }
    }
}
