<?php

namespace App\Services\User;

use App\Actions\User\ApplyAssistanceTableFilters;
use App\Actions\User\ApplyAssistanceTableSort;
use App\Actions\User\EvaluateAssistanceEligibility;
use App\Actions\User\GuardAssistanceEligibility;
use App\Actions\User\JoinAssistanceTableRelations;
use App\Models\Assistance;
use App\Models\AssistanceFieldValue;
use App\Models\AssistanceItem;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Beneficiary;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\RequestStatus;
use App\Models\RequestSubStatus;
use App\Models\User;
use App\Support\AssistanceItemOrigin;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssistanceService
{
    public function __construct(
        private JoinAssistanceTableRelations $joinAssistanceTableRelations,
        private ApplyAssistanceTableSort $applyAssistanceTableSort,
        private ApplyAssistanceTableFilters $applyAssistanceTableFilters,
        private ProgramFieldService $programFieldService,
        private GuardAssistanceEligibility $guardAssistanceEligibility,
        private EvaluateAssistanceEligibility $evaluateAssistanceEligibility,
    ) {}

    public function ensureProgramIsOpen(Program $program, string $message): void
    {
        if ($program->is_closed) {
            throw ValidationException::withMessages([
                'program' => [$message],
            ]);
        }
    }

    /**
     * @param  array{
     *     beneficiary_id: int,
     *     mode_of_request_id: int,
     *     recorded_at: string,
     *     remark?: string|null,
     *     item_details: list<array{
     *         item_id: int,
     *         quantity: int,
     *         specification?: string|null
     *     }>,
     *     field_values?: list<array{
     *         program_field_id: int,
     *         value?: string|null
     *     }>,
     *     eligibility_override_reason?: string|null
     * }  $validated
     */
    public function create(Program $program, User $user, array $validated): Assistance
    {
        return DB::transaction(function () use ($program, $user, $validated): Assistance {
            $beneficiary = Beneficiary::query()
                ->lockForUpdate()
                ->findOrFail($validated['beneficiary_id']);

            $recordedAt = Carbon::parse($validated['recorded_at']);
            $overrideReason = $this->nullableOverrideReason($validated['eligibility_override_reason'] ?? null);

            $this->guardAssistanceEligibility->assert(
                $program,
                $beneficiary,
                $this->normalizedItemDetails($validated['item_details']),
                $overrideReason,
                $recordedAt,
            );

            $assistance = Assistance::query()->create([
                'program_id' => $program->id,
                'beneficiary_id' => $beneficiary->id,
                'mode_of_request_id' => $validated['mode_of_request_id'],
                'date_requested' => $recordedAt->toDateString(),
                'remark' => $validated['remark'] ?? null,
                'eligibility_override_reason' => $overrideReason,
                'user_id' => $user->id,
            ]);

            $inProgressSubStatusId = RequestSubStatus::query()
                ->where('name', 'In Progress')
                ->value('id');

            if ($inProgressSubStatusId !== null) {
                AssistanceRequestSubStatus::query()->create([
                    'assistance_id' => $assistance->id,
                    'request_sub_status_id' => $inProgressSubStatusId,
                    'remark' => $overrideReason !== null
                        ? 'Eligibility override: '.$overrideReason
                        : null,
                    'recorded_at' => $recordedAt,
                ]);
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

            return $assistance;
        });
    }

    /**
     * Editing only ever touches the outstanding request; released and substituted lines are
     * historical facts and are left out of the payload.
     *
     * @return array<string, mixed>
     */
    public function editPayload(Assistance $assistance): array
    {
        $assistance->load([
            'beneficiary:id,cais_number,name',
            'assistanceItem' => static fn ($query) => $query->awaitingRelease(),
            'fieldValues:id,assistance_id,program_field_id,value',
        ]);

        $beneficiary = $assistance->beneficiary;

        return [
            'id' => $assistance->id,
            'beneficiary_id' => $assistance->beneficiary_id,
            'beneficiary' => $beneficiary instanceof Beneficiary ? [
                'id' => $beneficiary->id,
                'cais_number' => $beneficiary->cais_number,
                'name' => $beneficiary->name,
                'label' => trim("{$beneficiary->cais_number} - {$beneficiary->name}"),
            ] : null,
            'mode_of_request_id' => $assistance->mode_of_request_id,
            'remark' => $assistance->remark,
            'item_details' => $assistance->assistanceItem
                ->map(static fn (AssistanceItem $assistanceItem): array => [
                    'item_id' => $assistanceItem->item_id,
                    'quantity' => $assistanceItem->quantity ?? 1,
                    'specification' => $assistanceItem->specification,
                ])
                ->values()
                ->all(),
            'field_values' => $assistance->fieldValues
                ->map(static fn (AssistanceFieldValue $fieldValue): array => [
                    'program_field_id' => $fieldValue->program_field_id,
                    'value' => $fieldValue->value,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<string>  $statuses
     * @param  list<string>  $modes
     */
    public function paginatedForProgram(
        Program $program,
        string $sort,
        string $direction,
        int $perPage,
        string $search,
        array $statuses,
        array $modes,
    ): LengthAwarePaginator {
        $assistancesQuery = $this->baseProgramTableQuery(
            $program,
            $search,
            $statuses,
            $modes,
            $sort,
            $direction,
        );

        $programFieldService = $this->programFieldService;

        return $assistancesQuery
            ->paginate($perPage)
            ->withQueryString()
            ->through(static function (Assistance $assistance) use ($programFieldService): array {
                $formatDate = static function ($value): ?string {
                    if ($value === null) {
                        return null;
                    }

                    return Carbon::parse($value)->toDateString();
                };

                $requestSubStatusId = $assistance->request_sub_status_id;
                $requestSubStatus = $assistance->request_sub_status_name;
                $requestStatus = $assistance->request_status_name;
                $requestSubStatusRecordedAt = $assistance->request_sub_status_recorded_at;

                $status = $requestSubStatus
                    ?? $requestStatus
                    ?? 'Unrequested';

                $fieldValues = [];

                foreach ($assistance->fieldValues as $fieldValue) {
                    $field = $fieldValue->programField;

                    if ($field === null) {
                        continue;
                    }

                    $fieldValues[$field->key] = $programFieldService->formatDisplayValue(
                        $field,
                        $fieldValue->value,
                    );
                }

                return [
                    'id' => $assistance->id,
                    'beneficiary_id' => $assistance->beneficiary_id,
                    'cais_number' => $assistance->beneficiary_cais_number ?? '—',
                    'beneficiary_name' => $assistance->beneficiary_name ?? '—',
                    'items' => $assistance->assistanceItem
                        ->map(static fn (AssistanceItem $assistanceItem): array => [
                            'id' => $assistanceItem->id,
                            'item_id' => $assistanceItem->item_id,
                            'name' => $assistanceItem->item?->name ?? '—',
                            'quantity' => $assistanceItem->quantity,
                            'unit' => $assistanceItem->item?->unitMeasurement?->name,
                            'specification' => $assistanceItem->specification,
                            'is_received' => (bool) $assistanceItem->is_received,
                            'origin' => $assistanceItem->origin,
                            'requested_quantity' => (int) $assistanceItem->requested_quantity,
                            'is_substituted' => $assistanceItem->isSubstituted(),
                            'fulfillment_reason' => $assistanceItem->fulfillment_reason,
                        ])
                        ->values()
                        ->all(),
                    'mode_of_request' => $assistance->mode_of_request_name ?? '—',
                    'date_requested' => $formatDate($assistance->date_requested),
                    'date_delivered' => $formatDate($assistance->date_delivered),
                    'request_status' => $requestStatus,
                    'request_sub_status_id' => $requestSubStatusId !== null
                        ? (int) $requestSubStatusId
                        : null,
                    'request_sub_status' => $requestSubStatus,
                    'request_sub_status_recorded_at' => $requestSubStatusRecordedAt !== null
                        ? Carbon::parse($requestSubStatusRecordedAt)->toIso8601String()
                        : null,
                    'status' => $status,
                    'remark' => $assistance->remark,
                    'field_values' => $fieldValues,
                ];
            });
    }

    /**
     * @param  list<string>  $statuses
     * @param  list<string>  $modes
     * @return Collection<int, Assistance>
     */
    public function exportRowsForProgram(
        Program $program,
        string $sort,
        string $direction,
        string $search,
        array $statuses,
        array $modes,
    ): Collection {
        return $this->baseProgramTableQuery(
            $program,
            $search,
            $statuses,
            $modes,
            $sort,
            $direction,
        )->get();
    }

    /**
     * @param  list<string>  $statuses
     * @param  list<string>  $modes
     * @return Builder<Assistance>
     */
    private function baseProgramTableQuery(
        Program $program,
        string $search,
        array $statuses,
        array $modes,
        string $sort,
        string $direction,
    ): Builder {
        $assistancesQuery = Assistance::query()
            ->where('assistances.program_id', $program->id);

        ($this->joinAssistanceTableRelations)($assistancesQuery);

        $assistancesQuery->select([
            'assistances.id',
            'assistances.beneficiary_id',
            'assistances.mode_of_request_id',
            'assistances.date_requested',
            'assistances.date_delivered',
            'assistances.remark',
            'beneficiaries.cais_number as beneficiary_cais_number',
            'beneficiaries.name as beneficiary_name',
            'mode_of_requests.name as mode_of_request_name',
            'rss.id as request_sub_status_id',
            'rss.name as request_sub_status_name',
            'rs.name as request_status_name',
            'assistances.current_status_recorded_at as request_sub_status_recorded_at',
        ])->with([
            'assistanceItem',
            'assistanceItem.item:id,name,item_unit_measurement_id',
            'assistanceItem.item.unitMeasurement:id,name',
            'fieldValues:id,assistance_id,program_field_id,value',
            'fieldValues.programField:id,key,type,label',
        ]);

        ($this->applyAssistanceTableFilters)($assistancesQuery, $search, $statuses, $modes);
        ($this->applyAssistanceTableSort)($assistancesQuery, $sort, $direction);

        return $assistancesQuery;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function modeOptions(Program $program): array
    {
        return ModeOfRequest::query()
            ->join('assistances', 'assistances.mode_of_request_id', '=', 'mode_of_requests.id')
            ->where('assistances.program_id', $program->id)
            ->distinct()
            ->orderBy('mode_of_requests.name')
            ->pluck('mode_of_requests.name')
            ->map(static fn (string $name): array => [
                'label' => $name,
                'value' => $name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function statusOptions(Program $program): array
    {
        return RequestStatus::query()
            ->join('request_sub_statuses as rss', 'rss.request_status_id', '=', 'request_statuses.id')
            ->join('assistances', 'assistances.current_request_sub_status_id', '=', 'rss.id')
            ->where('assistances.program_id', $program->id)
            ->whereNull('assistances.deleted_at')
            ->distinct()
            ->orderBy('request_statuses.name')
            ->pluck('request_statuses.name')
            ->map(static fn (string $name): array => [
                'label' => $name,
                'value' => $name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function modesOfRequestForSelect(): array
    {
        return ModeOfRequest::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, request_status: string|null, label: string}>
     */
    public function requestSubStatusesForSelect(): array
    {
        return RequestSubStatus::query()
            ->join(
                'request_statuses',
                'request_statuses.id',
                '=',
                'request_sub_statuses.request_status_id',
            )
            ->orderBy('request_statuses.name')
            ->orderBy('request_sub_statuses.name')
            ->get([
                'request_sub_statuses.id',
                'request_sub_statuses.name',
                'request_statuses.name as request_status_name',
            ])
            ->map(static fn ($subStatus): array => [
                'id' => (int) $subStatus->id,
                'name' => $subStatus->name,
                'request_status' => $subStatus->request_status_name,
                'label' => "{$subStatus->request_status_name} — {$subStatus->name}",
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{item_id?: int, quantity?: int}>  $itemDetails
     * @return array{
     *     findings: list<array{severity: string, code: string, message: string, assistance_id?: int, item_id?: int}>,
     *     history: list<array{
     *         id: int,
     *         program_id: int,
     *         program_name: string,
     *         status: string|null,
     *         date_requested: string|null,
     *         date_delivered: string|null,
     *         items: list<array{name: string, quantity: int, is_received: bool}>
     *     }>
     * }
     */
    public function eligibilityPreview(
        Program $program,
        Beneficiary $beneficiary,
        array $itemDetails = [],
        ?DateTimeInterface $asOf = null,
        ?int $exceptAssistanceId = null,
    ): array {
        $asOf = $asOf instanceof Carbon ? $asOf : Carbon::parse($asOf ?? now());

        $findings = ($this->evaluateAssistanceEligibility)(
            $program,
            $beneficiary,
            $this->normalizedItemDetails($itemDetails),
            $asOf,
            $exceptAssistanceId,
        );

        $history = Assistance::query()
            ->where('beneficiary_id', $beneficiary->id)
            ->whereHas('program', function ($query) use ($program): void {
                $query->where('department_id', $program->department_id);
            })
            ->when(
                $exceptAssistanceId !== null,
                fn ($query) => $query->whereKeyNot($exceptAssistanceId),
            )
            ->with([
                'program:id,name',
                'currentRequestSubStatus:id,name,request_status_id',
                'currentRequestSubStatus.requestStatus:id,name',
                'assistanceItem:id,assistance_id,item_id,quantity,is_received',
                'assistanceItem.item:id,name',
            ])
            ->orderByDesc('date_requested')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'findings' => $findings,
            'history' => $history
                ->map(static function (Assistance $assistance): array {
                    $status = $assistance->currentRequestSubStatus?->requestStatus?->name
                        ?? $assistance->currentRequestSubStatus?->name;

                    return [
                        'id' => $assistance->id,
                        'program_id' => $assistance->program_id,
                        'program_name' => $assistance->program?->name ?? '—',
                        'status' => $status,
                        'date_requested' => $assistance->date_requested !== null
                            ? Carbon::parse($assistance->date_requested)->toDateString()
                            : null,
                        'date_delivered' => $assistance->date_delivered !== null
                            ? Carbon::parse($assistance->date_delivered)->toDateString()
                            : null,
                        'items' => $assistance->assistanceItem
                            ->map(static fn (AssistanceItem $item): array => [
                                'name' => $item->item?->name ?? '—',
                                'quantity' => (int) $item->quantity,
                                'is_received' => (bool) $item->is_received,
                            ])
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<array{item_id?: int, quantity?: int}>  $itemDetails
     * @return list<array{item_id: int, quantity: int}>
     */
    private function normalizedItemDetails(array $itemDetails): array
    {
        return collect($itemDetails)
            ->map(static fn (array $row): array => [
                'item_id' => (int) ($row['item_id'] ?? 0),
                'quantity' => (int) ($row['quantity'] ?? 0),
            ])
            ->filter(static fn (array $row): bool => $row['item_id'] > 0 && $row['quantity'] > 0)
            ->values()
            ->all();
    }

    private function nullableOverrideReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $trimmed = trim($reason);

        return $trimmed === '' ? null : $trimmed;
    }
}
