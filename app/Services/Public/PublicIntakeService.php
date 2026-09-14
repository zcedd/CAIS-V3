<?php

namespace App\Services\Public;

use App\Actions\User\EvaluateAssistanceEligibility;
use App\Actions\User\FindPossibleDuplicateBeneficiaries;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Beneficiary;
use App\Models\Individual;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\RequestStatus;
use App\Models\RequestSubStatus;
use App\Services\User\IndividualBeneficiaryService;
use App\Services\User\ProgramFieldService;
use App\Support\AssistanceItemOrigin;
use App\Support\IdentityNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicIntakeService
{
    public const IntentSave = 'save';

    public const IntentSubmit = 'submit';

    public function __construct(
        private FindPossibleDuplicateBeneficiaries $findPossibleDuplicateBeneficiaries,
        private IndividualBeneficiaryService $individualBeneficiaryService,
        private EvaluateAssistanceEligibility $evaluateAssistanceEligibility,
        private ProgramFieldService $programFieldService,
    ) {}

    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     birthday: string,
     *     address_barangay_id: int,
     *     middle_name?: string|null
     * }  $identity
     * @return list<array{
     *     id: int,
     *     cais_number: string|null,
     *     name: string,
     *     birth_year: int|null,
     *     barangay: string|null
     * }>
     */
    public function highScoreMatches(array $identity): array
    {
        $matches = ($this->findPossibleDuplicateBeneficiaries)([
            'type' => 'individual',
            'first_name' => $identity['first_name'],
            'last_name' => $identity['last_name'],
            'birthday' => $identity['birthday'],
            'address_barangay_id' => $identity['address_barangay_id'],
        ]);

        return collect($matches)
            ->where('score', 'high')
            ->map(function (array $match): array {
                $birthday = $match['birthday'] ?? null;

                return [
                    'id' => (int) $match['id'],
                    'cais_number' => $match['cais_number'],
                    'name' => $match['name'],
                    'birth_year' => $birthday !== null && $birthday !== ''
                        ? Carbon::parse($birthday)->year
                        : null,
                    'barangay' => $match['barangay'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *     assistance: Assistance,
     *     cais_number: string,
     *     intent: string
     * }
     */
    public function submit(Program $program, array $validated): array
    {
        if (! $program->acceptsPublicIntake()) {
            abort(404);
        }

        $intent = $validated['intent'];
        $identity = $this->identityFromValidated($validated);
        $matches = $this->highScoreMatches($identity);
        $confirmedId = isset($validated['confirmed_beneficiary_id'])
            ? (int) $validated['confirmed_beneficiary_id']
            : null;
        $createNew = (bool) ($validated['create_new'] ?? false);

        if ($matches !== [] && $confirmedId === null && ! $createNew) {
            throw ValidationException::withMessages([
                'confirmed_beneficiary_id' => 'Confirm whether one of these records is you, or create a new profile.',
            ]);
        }

        return DB::transaction(function () use ($program, $validated, $identity, $matches, $confirmedId, $createNew, $intent): array {
            $beneficiary = $this->resolveBeneficiary($identity, $validated, $matches, $confirmedId, $createNew);
            $draft = $this->existingDraft($program, $beneficiary);

            $this->assertEligibility(
                $program,
                $beneficiary,
                $this->normalizedItemDetails($validated['item_details']),
                $draft?->id,
            );

            $assistance = $draft ?? Assistance::query()->create([
                'program_id' => $program->id,
                'beneficiary_id' => $beneficiary->id,
                'mode_of_request_id' => $this->onlineModeOfRequestId(),
                'date_requested' => now()->toDateString(),
                'remark' => $validated['remark'] ?? null,
                'user_id' => null,
            ]);

            if ($draft instanceof Assistance) {
                $assistance->update([
                    'remark' => $validated['remark'] ?? $assistance->remark,
                    'date_requested' => now()->toDateString(),
                ]);
                $assistance->assistanceItem()->delete();
            }

            foreach ($validated['item_details'] as $itemDetail) {
                AssistanceItem::query()->create([
                    'assistance_id' => $assistance->id,
                    'item_id' => $itemDetail['item_id'],
                    'origin' => AssistanceItemOrigin::Requested,
                    'quantity' => $itemDetail['quantity'],
                    'requested_quantity' => $itemDetail['quantity'],
                    'specification' => $itemDetail['specification'] ?? null,
                    'is_received' => false,
                ]);
            }

            $this->programFieldService->syncValuesForAssistance(
                $assistance,
                $validated['field_values'] ?? [],
            );

            $subStatusName = $intent === self::IntentSave ? 'Saved For Later' : 'Awaiting Review';
            $parentName = $intent === self::IntentSave ? 'Draft' : 'Submitted';

            AssistanceRequestSubStatus::query()->create([
                'assistance_id' => $assistance->id,
                'request_sub_status_id' => $this->subStatusId($subStatusName, $parentName),
                'remark' => null,
                'recorded_at' => now(),
            ]);

            $beneficiary->refresh();

            return [
                'assistance' => $assistance->refresh(),
                'cais_number' => (string) $beneficiary->cais_number,
                'intent' => $intent,
            ];
        });
    }

    /**
     * @return array{
     *     cais_number: string,
     *     beneficiary_name: string,
     *     requests: list<array{
     *         id: int,
     *         program_id: int,
     *         program_name: string,
     *         status: string,
     *         date_requested: string|null,
     *         can_resume: bool
     *     }>
     * }
     */
    public function track(string $caisNumber, string $lastName): array
    {
        $beneficiary = $this->beneficiaryForTracking($caisNumber, $lastName);

        $requests = Assistance::query()
            ->with([
                'program:id,name,public_intake,is_closed,kind,is_organization',
                'currentRequestSubStatus:id,name',
            ])
            ->where('beneficiary_id', $beneficiary->id)
            ->whereNull('user_id')
            ->orderByDesc('id')
            ->get();

        return [
            'cais_number' => (string) $beneficiary->cais_number,
            'beneficiary_name' => (string) $beneficiary->name,
            'requests' => $requests
                ->map(function (Assistance $assistance): array {
                    $status = $assistance->currentRequestSubStatus?->name ?? 'Submitted';

                    return [
                        'id' => $assistance->id,
                        'program_id' => $assistance->program_id,
                        'program_name' => $assistance->program?->name ?? 'Program',
                        'status' => $status,
                        'date_requested' => $assistance->date_requested
                            ? Carbon::parse($assistance->date_requested)->toDateString()
                            : null,
                        'can_resume' => $status === 'Saved For Later'
                            && $assistance->program?->acceptsPublicIntake() === true,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resumePayload(Program $program, string $caisNumber, string $lastName): ?array
    {
        $beneficiary = $this->beneficiaryForTracking($caisNumber, $lastName);
        $draft = $this->existingDraft($program, $beneficiary);

        if (! $draft instanceof Assistance) {
            return null;
        }

        $draft->load([
            'assistanceItem:id,assistance_id,item_id,quantity,specification',
            'fieldValues:id,assistance_id,program_field_id,value',
        ]);

        $individual = $beneficiary->beneficiable;

        return [
            'cais_number' => $beneficiary->cais_number,
            'confirmed_beneficiary_id' => $beneficiary->id,
            'identity' => $individual instanceof Individual ? [
                'first_name' => $individual->first_name,
                'middle_name' => $individual->middle_name,
                'last_name' => $individual->last_name,
                'suffix' => $individual->suffix,
                'birthday' => $individual->birthday,
                'sex' => $individual->sex,
                'other_address' => $individual->other_address,
                'mobile_number' => $individual->mobile_number,
                'indigenous' => (bool) $individual->indigenous,
                'ethnicity' => $individual->ethnicity,
                'pwd' => (bool) $individual->pwd,
                'is_4ps_beneficiary' => (bool) $individual->is_4ps_beneficiary,
                'is_solo_parent' => (bool) $individual->is_solo_parent,
                'address_barangay_id' => $individual->address_barangay_id,
            ] : null,
            'item_details' => $draft->assistanceItem
                ->map(static fn (AssistanceItem $item): array => [
                    'item_id' => $item->item_id,
                    'quantity' => $item->quantity,
                    'specification' => $item->specification,
                ])
                ->values()
                ->all(),
            'field_values' => $draft->fieldValues
                ->mapWithKeys(static fn ($value): array => [
                    (int) $value->program_field_id => (string) ($value->value ?? ''),
                ])
                ->all(),
            'remark' => $draft->remark,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *     first_name: string,
     *     middle_name: string|null,
     *     last_name: string,
     *     suffix: string|null,
     *     birthday: string,
     *     sex: string,
     *     other_address: string|null,
     *     mobile_number: string|null,
     *     indigenous: bool,
     *     ethnicity: string|null,
     *     pwd: bool,
     *     is_4ps_beneficiary: bool,
     *     is_solo_parent: bool,
     *     address_barangay_id: int
     * }
     */
    private function identityFromValidated(array $validated): array
    {
        return [
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'suffix' => $validated['suffix'] ?? null,
            'birthday' => $validated['birthday'],
            'sex' => $validated['sex'],
            'other_address' => $validated['other_address'] ?? null,
            'mobile_number' => $validated['mobile_number'] ?? null,
            'indigenous' => (bool) ($validated['indigenous'] ?? false),
            'ethnicity' => $validated['ethnicity'] ?? null,
            'pwd' => (bool) ($validated['pwd'] ?? false),
            'is_4ps_beneficiary' => (bool) ($validated['is_4ps_beneficiary'] ?? false),
            'is_solo_parent' => (bool) ($validated['is_solo_parent'] ?? false),
            'address_barangay_id' => (int) $validated['address_barangay_id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $validated
     * @param  list<array{id: int, cais_number: string|null, name: string, birth_year: int|null, barangay: string|null}>  $matches
     */
    private function resolveBeneficiary(
        array $identity,
        array $validated,
        array $matches,
        ?int $confirmedId,
        bool $createNew,
    ): Beneficiary {
        if (! $createNew && $confirmedId !== null) {
            $allowedIds = collect($matches)->pluck('id')->all();

            if (! in_array($confirmedId, $allowedIds, true)) {
                throw ValidationException::withMessages([
                    'confirmed_beneficiary_id' => 'The selected profile does not match the details you entered.',
                ]);
            }

            return Beneficiary::query()->findOrFail($confirmedId);
        }

        $individual = $this->individualBeneficiaryService->create($identity);
        $individual->loadMissing('beneficiaryRecord');

        $beneficiary = $individual->beneficiaryRecord;

        if (! $beneficiary instanceof Beneficiary) {
            throw ValidationException::withMessages([
                'first_name' => 'Could not create a beneficiary profile. Please try again.',
            ]);
        }

        return $beneficiary;
    }

    private function existingDraft(Program $program, Beneficiary $beneficiary): ?Assistance
    {
        return Assistance::query()
            ->where('program_id', $program->id)
            ->where('beneficiary_id', $beneficiary->id)
            ->whereHas('currentRequestSubStatus', static fn ($query) => $query->where('name', 'Saved For Later'))
            ->latest('id')
            ->first();
    }

    /**
     * @param  list<array{item_id: int, quantity: int}>  $itemDetails
     */
    private function assertEligibility(
        Program $program,
        Beneficiary $beneficiary,
        array $itemDetails,
        ?int $exceptAssistanceId,
    ): void {
        $findings = ($this->evaluateAssistanceEligibility)(
            $program,
            $beneficiary,
            $itemDetails,
            now(),
            $exceptAssistanceId,
        );

        if ($findings === []) {
            return;
        }

        session()->flash('eligibility_findings', $findings);

        throw ValidationException::withMessages([
            'eligibility' => collect($findings)->pluck('message')->implode(' '),
        ]);
    }

    /**
     * @param  list<array{item_id: int, quantity: int, specification?: string|null}>  $itemDetails
     * @return list<array{item_id: int, quantity: int}>
     */
    private function normalizedItemDetails(array $itemDetails): array
    {
        return collect($itemDetails)
            ->map(static fn (array $row): array => [
                'item_id' => (int) $row['item_id'],
                'quantity' => (int) $row['quantity'],
            ])
            ->values()
            ->all();
    }

    private function beneficiaryForTracking(string $caisNumber, string $lastName): Beneficiary
    {
        $beneficiary = Beneficiary::query()
            ->where('cais_number', $caisNumber)
            ->first();

        if (! $beneficiary instanceof Beneficiary) {
            throw ValidationException::withMessages([
                'cais_number' => 'No request was found for those details.',
            ]);
        }

        $beneficiable = $beneficiary->beneficiable;

        if (! $beneficiable instanceof Individual) {
            throw ValidationException::withMessages([
                'cais_number' => 'No request was found for those details.',
            ]);
        }

        if (! IdentityNormalizer::namesMatch($beneficiable->last_name, $lastName)) {
            throw ValidationException::withMessages([
                'cais_number' => 'No request was found for those details.',
            ]);
        }

        return $beneficiary;
    }

    private function onlineModeOfRequestId(): int
    {
        return (int) ModeOfRequest::query()->firstOrCreate(
            ['name' => 'Online'],
        )->id;
    }

    private function subStatusId(string $name, string $parentName): int
    {
        $existing = RequestSubStatus::query()->where('name', $name)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $parentId = RequestStatus::query()->where('name', $parentName)->value('id');

        if ($parentId === null) {
            $parentId = RequestStatus::query()->create(['name' => $parentName])->id;
        }

        return (int) RequestSubStatus::query()->create([
            'name' => $name,
            'request_status_id' => $parentId,
            'description' => null,
        ])->id;
    }
}
