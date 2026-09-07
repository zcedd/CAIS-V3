<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Beneficiary;
use App\Models\Individual;
use App\Models\Item;
use App\Models\Program;
use App\Models\ProgramEligibilityRule;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class EvaluateAssistanceEligibility
{
    /**
     * @param  list<array{item_id: int, quantity: int}>  $itemDetails
     * @return list<array{
     *     severity: 'hard'|'soft',
     *     code: string,
     *     message: string,
     *     assistance_id?: int,
     *     item_id?: int
     * }>
     */
    public function __invoke(
        Program $program,
        Beneficiary $beneficiary,
        array $itemDetails = [],
        ?DateTimeInterface $asOf = null,
        ?int $exceptAssistanceId = null,
    ): array {
        $asOf = $asOf instanceof Carbon ? $asOf : Carbon::parse($asOf ?? now());
        $eligibilityProgram = $program->eligibilityProgram();

        if ($eligibilityProgram->id !== $program->id) {
            $eligibilityProgram->loadMissing(['eligibilityRule', 'itemCaps.item:id,name']);
            $program->setRelation('eligibilityRule', $eligibilityProgram->eligibilityRule);
            $program->setRelation('itemCaps', $eligibilityProgram->itemCaps);
        } else {
            $program->loadMissing(['eligibilityRule', 'itemCaps.item:id,name']);
        }

        $beneficiary->loadMissing('beneficiable');

        $findings = [
            ...$this->demographicFindings($program, $beneficiary),
            ...$this->openRequestFindings($program, $beneficiary, $exceptAssistanceId),
            ...$this->cooldownFindings($program, $beneficiary, $asOf, $exceptAssistanceId),
            ...$this->itemCapFindings($program, $beneficiary, $itemDetails, $asOf, $exceptAssistanceId),
        ];

        return array_values($findings);
    }

    /**
     * @return list<array{severity: 'hard', code: string, message: string}>
     */
    private function demographicFindings(Program $program, Beneficiary $beneficiary): array
    {
        if ($program->is_organization) {
            return [];
        }

        $rule = $program->eligibilityRule;

        if (! $rule instanceof ProgramEligibilityRule || ! $rule->hasDemographicRequirements()) {
            return [];
        }

        $individual = $beneficiary->beneficiable;

        if (! $individual instanceof Individual) {
            return [[
                'severity' => 'hard',
                'code' => 'demographic_type',
                'message' => 'This program is limited to individual beneficiaries who match the demographic filters.',
            ]];
        }

        $findings = [];

        if ($rule->require_pwd && ! $individual->pwd) {
            $findings[] = [
                'severity' => 'hard',
                'code' => 'demographic_pwd',
                'message' => 'This program is limited to PWD beneficiaries.',
            ];
        }

        if ($rule->require_4ps && ! $individual->is_4ps_beneficiary) {
            $findings[] = [
                'severity' => 'hard',
                'code' => 'demographic_4ps',
                'message' => 'This program is limited to 4Ps beneficiaries.',
            ];
        }

        if ($rule->require_solo_parent && ! $individual->is_solo_parent) {
            $findings[] = [
                'severity' => 'hard',
                'code' => 'demographic_solo_parent',
                'message' => 'This program is limited to solo parent beneficiaries.',
            ];
        }

        if ($rule->require_indigenous && ! $individual->indigenous) {
            $findings[] = [
                'severity' => 'hard',
                'code' => 'demographic_indigenous',
                'message' => 'This program is limited to indigenous peoples beneficiaries.',
            ];
        }

        return $findings;
    }

    /**
     * @return list<array{severity: 'soft', code: string, message: string, assistance_id: int}>
     */
    private function openRequestFindings(
        Program $program,
        Beneficiary $beneficiary,
        ?int $exceptAssistanceId,
    ): array {
        $openAssistances = Assistance::query()
            ->where('beneficiary_id', $beneficiary->id)
            ->whereIn('program_id', $program->familyIds())
            ->pending()
            ->when(
                $exceptAssistanceId !== null,
                fn ($query) => $query->whereKeyNot($exceptAssistanceId),
            )
            ->get(['id']);

        return $openAssistances
            ->map(static fn (Assistance $assistance): array => [
                'severity' => 'soft',
                'code' => 'open_request',
                'message' => 'This beneficiary already has an open request in this program.',
                'assistance_id' => $assistance->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{severity: 'soft', code: string, message: string, assistance_id?: int}>
     */
    private function cooldownFindings(
        Program $program,
        Beneficiary $beneficiary,
        Carbon $asOf,
        ?int $exceptAssistanceId,
    ): array {
        $rule = $program->eligibilityRule;
        $cooldownDays = $rule?->cooldown_days;

        if ($cooldownDays === null || $cooldownDays < 1) {
            return [];
        }

        $lastDelivered = Assistance::query()
            ->where('beneficiary_id', $beneficiary->id)
            ->whereIn('program_id', $program->familyIds())
            ->whereNotNull('date_delivered')
            ->when(
                $exceptAssistanceId !== null,
                fn ($query) => $query->whereKeyNot($exceptAssistanceId),
            )
            ->orderByDesc('date_delivered')
            ->first(['id', 'date_delivered']);

        if ($lastDelivered === null || $lastDelivered->date_delivered === null) {
            return [];
        }

        $eligibleOn = Carbon::parse($lastDelivered->date_delivered)->addDays($cooldownDays);

        if ($asOf->copy()->startOfDay()->gte($eligibleOn->copy()->startOfDay())) {
            return [];
        }

        $remainingDays = max(
            0,
            (int) ceil($asOf->copy()->startOfDay()->diffInDays($eligibleOn->copy()->startOfDay(), false)),
        );

        return [[
            'severity' => 'soft',
            'code' => 'cooldown',
            'message' => "This beneficiary is within the {$cooldownDays}-day cooldown ({$remainingDays} day(s) remaining).",
            'assistance_id' => $lastDelivered->id,
        ]];
    }

    /**
     * @param  list<array{item_id: int, quantity: int}>  $itemDetails
     * @return list<array{severity: 'soft', code: string, message: string, item_id: int}>
     */
    private function itemCapFindings(
        Program $program,
        Beneficiary $beneficiary,
        array $itemDetails,
        Carbon $asOf,
        ?int $exceptAssistanceId,
    ): array {
        if ($itemDetails === []) {
            return [];
        }

        $caps = $program->itemCaps->keyBy('item_id');

        if ($caps->isEmpty()) {
            return [];
        }

        $year = (int) $asOf->year;
        $requestedByItem = [];

        foreach ($itemDetails as $itemDetail) {
            $itemId = (int) $itemDetail['item_id'];
            $requestedByItem[$itemId] = ($requestedByItem[$itemId] ?? 0) + (int) $itemDetail['quantity'];
        }

        $findings = [];

        foreach ($requestedByItem as $itemId => $requestedQuantity) {
            $cap = $caps->get($itemId);

            if ($cap === null) {
                continue;
            }

            $releasedQuantity = (int) AssistanceItem::query()
                ->where('item_id', $itemId)
                ->where('is_received', true)
                ->whereHas('assistance', function ($query) use ($beneficiary, $program, $year, $exceptAssistanceId): void {
                    $query
                        ->where('beneficiary_id', $beneficiary->id)
                        ->whereIn('program_id', $program->familyIds())
                        ->where(function ($yearQuery) use ($year): void {
                            $yearQuery
                                ->whereYear('date_delivered', $year)
                                ->orWhere(function ($requestedYearQuery) use ($year): void {
                                    $requestedYearQuery
                                        ->whereNull('date_delivered')
                                        ->whereYear('date_requested', $year);
                                });
                        })
                        ->when(
                            $exceptAssistanceId !== null,
                            fn ($exceptQuery) => $exceptQuery->whereKeyNot($exceptAssistanceId),
                        );
                })
                ->sum('quantity');

            $projected = $releasedQuantity + $requestedQuantity;

            if ($projected <= $cap->max_released_per_year) {
                continue;
            }

            $itemName = $cap->item instanceof Item
                ? $cap->item->name
                : 'this item';

            $findings[] = [
                'severity' => 'soft',
                'code' => 'item_cap',
                'message' => "Requested {$itemName} would exceed the yearly released cap of {$cap->max_released_per_year} (already released: {$releasedQuantity}).",
                'item_id' => $itemId,
            ];
        }

        return $findings;
    }
}
