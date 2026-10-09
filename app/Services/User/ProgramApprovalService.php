<?php

namespace App\Services\User;

use App\Enums\ProgramApprovalAction;
use App\Enums\ProgramApprovalStatus;
use App\Enums\RequestSubStatusCode;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\Program;
use App\Models\ProgramApprovalEvent;
use App\Models\RequestSubStatus;
use App\Models\User;
use App\Services\Workflow\RequestStatusCatalog;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgramApprovalService
{
    public function __construct(
        private RequestStatusCatalog $statuses,
        private WorkflowEngine $workflowEngine,
    ) {}

    public function submit(Program $program, User $actor, ?string $remark): void
    {
        if ($program->isScheme()) {
            throw ValidationException::withMessages([
                'program' => 'Submit each batch separately for executive approval.',
            ]);
        }

        if (! in_array($program->approval_status, [
            ProgramApprovalStatus::Draft,
            ProgramApprovalStatus::Returned,
        ], true)) {
            throw ValidationException::withMessages([
                'program' => 'This program is not waiting for department endorsement.',
            ]);
        }

        if ($program->requiresBeneficiaries() && $this->verifiedCount($program) < 1) {
            throw ValidationException::withMessages([
                'program' => 'Add at least one verified beneficiary before submitting this program for executive approval.',
            ]);
        }

        if (! $program->requiresBeneficiaries() && $this->assistanceQuery($program)->exists()) {
            throw ValidationException::withMessages([
                'program' => 'This program is approved without a beneficiary list. Remove assistance records before submitting it.',
            ]);
        }

        DB::transaction(function () use ($program, $actor, $remark): void {
            $program->update([
                'approval_status' => ProgramApprovalStatus::AwaitingGovernor,
            ]);
            $this->record($program, $actor, ProgramApprovalAction::Submitted, $remark);
        });
    }

    public function approve(Program $program, User $actor, ?string $remark): void
    {
        $this->assertAwaiting($program);

        DB::transaction(function () use ($program, $actor, $remark): void {
            $program->update([
                'approval_status' => ProgramApprovalStatus::Approved,
            ]);
            $this->record($program, $actor, ProgramApprovalAction::Approved, $remark);

            if (! $program->requiresBeneficiaries()) {
                return;
            }

            $readyForRelease = $this->readyForReleaseStatus();

            foreach ($this->verifiedAssistances($program) as $assistance) {
                $this->workflowEngine->recordGovernorRelease($assistance, $readyForRelease, $actor);
            }
        });
    }

    public function returnToDepartment(Program $program, User $actor, ?string $remark): void
    {
        $this->assertAwaiting($program);

        DB::transaction(function () use ($program, $actor, $remark): void {
            $program->update([
                'approval_status' => ProgramApprovalStatus::Returned,
            ]);
            $this->record($program, $actor, ProgramApprovalAction::Returned, $remark);
        });
    }

    public function proposedCount(?int $departmentId): int
    {
        if ($departmentId === null) {
            return 0;
        }

        return Program::query()
            ->where('department_id', $departmentId)
            ->whereIn('approval_status', [
                ProgramApprovalStatus::Draft->value,
                ProgramApprovalStatus::Returned->value,
            ])
            ->count();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function departmentOptions(): array
    {
        return Department::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
            ])
            ->all();
    }

    public function awaitingGovernorCount(): int
    {
        return Program::query()
            ->where('approval_status', ProgramApprovalStatus::AwaitingGovernor->value)
            ->count();
    }

    public function verifiedCount(Program $program): int
    {
        return $this->verifiedQuery($program)->count();
    }

    /**
     * @param  list<string>  $types
     * @param  list<string>  $statuses
     * @param  list<string>  $approvals
     * @param  list<int>  $departmentIds
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForGovernor(
        string $search,
        array $types,
        array $statuses,
        array $approvals,
        array $departmentIds,
        int $perPage = 12,
    ): LengthAwarePaginator {
        return Program::query()
            ->select([
                'id',
                'name',
                'descriptions',
                'start_at',
                'end_at',
                'is_closed',
                'is_organization',
                'public_intake',
                'requires_beneficiaries',
                'department_id',
                'kind',
                'approval_status',
            ])
            ->with(['department:id,name,slug'])
            ->withCount([
                'batches',
                'batches as open_batches_count' => fn (Builder $query) => $query->where('is_closed', false),
            ])
            ->encodable()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('batch_name', 'like', '%'.$search.'%')
                        ->orWhereHas('parent', function (Builder $parentQuery) use ($search): void {
                            $parentQuery->where('name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when(
                count($types) === 1 && in_array('individual', $types, true),
                fn (Builder $query) => $query->where('is_organization', false),
            )
            ->when(
                count($types) === 1 && in_array('organization', $types, true),
                fn (Builder $query) => $query->where('is_organization', true),
            )
            ->when(
                count($statuses) === 1 && in_array('open', $statuses, true),
                fn (Builder $query) => $query->where('is_closed', false),
            )
            ->when(
                count($statuses) === 1 && in_array('closed', $statuses, true),
                fn (Builder $query) => $query->where('is_closed', true),
            )
            ->when(
                $approvals !== [],
                fn (Builder $query) => $query->whereIn('approval_status', $approvals),
            )
            ->when(
                $departmentIds !== [],
                fn (Builder $query) => $query->whereIn('department_id', $departmentIds),
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Program $program): array {
                $status = $program->approval_status instanceof ProgramApprovalStatus
                    ? $program->approval_status
                    : ProgramApprovalStatus::Approved;

                return [
                    'id' => $program->id,
                    'name' => $program->name,
                    'descriptions' => $program->descriptions,
                    'start_at' => $program->start_at?->format('M d, Y'),
                    'end_at' => $program->end_at?->format('M d, Y'),
                    'is_closed' => (bool) $program->is_closed,
                    'is_organization' => (bool) $program->is_organization,
                    'public_intake' => (bool) $program->public_intake,
                    'requires_beneficiaries' => $program->requiresBeneficiaries(),
                    'beneficiary_approval_label' => $program->beneficiaryApprovalLabel(),
                    'kind' => $program->kind?->value,
                    'batches_count' => (int) $program->batches_count,
                    'open_batches_count' => (int) $program->open_batches_count,
                    'approval_status' => $status->value,
                    'approval_label' => $status->label(),
                    'department' => $program->department === null ? null : [
                        'id' => $program->department->id,
                        'name' => $program->department->name,
                        'slug' => $program->department->slug,
                    ],
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function showPayload(Program $program): array
    {
        $program->loadMissing('department:id,name,slug');
        $status = $program->approval_status instanceof ProgramApprovalStatus
            ? $program->approval_status
            : ProgramApprovalStatus::Approved;

        return [
            'id' => $program->id,
            'name' => $program->name,
            'descriptions' => $program->descriptions,
            'kind' => $program->kind?->value,
            'approval_status' => $status->value,
            'approval_label' => $status->label(),
            'requires_beneficiaries' => $program->requiresBeneficiaries(),
            'beneficiary_approval_label' => $program->beneficiaryApprovalLabel(),
            'department' => $program->department === null ? null : [
                'id' => $program->department->id,
                'name' => $program->department->name,
            ],
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateBeneficiaries(Program $program): LengthAwarePaginator
    {
        return $this->assistanceQuery($program)
            ->with([
                'beneficiary:id,name,cais_number',
                'currentRequestSubStatus.requestStatus',
            ])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Assistance $assistance): array {
                $subStatus = $assistance->currentRequestSubStatus;

                return [
                    'id' => $assistance->id,
                    'beneficiary_name' => $assistance->beneficiary?->name,
                    'cais_number' => $assistance->beneficiary?->cais_number,
                    'status' => $subStatus?->requestStatus?->name,
                    'sub_status' => $subStatus?->name,
                ];
            });
    }

    private function assertAwaiting(Program $program): void
    {
        if ($program->approval_status !== ProgramApprovalStatus::AwaitingGovernor) {
            throw ValidationException::withMessages([
                'program' => 'This program is not awaiting executive approval.',
            ]);
        }
    }

    /**
     * @return list<Assistance>
     */
    private function verifiedAssistances(Program $program): array
    {
        return $this->verifiedQuery($program)->get()->all();
    }

    /**
     * @return Builder<Assistance>
     */
    private function verifiedQuery(Program $program): Builder
    {
        return $this->assistanceQuery($program)->verified();
    }

    /**
     * @return Builder<Assistance>
     */
    private function assistanceQuery(Program $program): Builder
    {
        return Assistance::query()->where('program_id', $program->id);
    }

    private function readyForReleaseStatus(): RequestSubStatus
    {
        return RequestSubStatus::query()->findOrFail(
            $this->statuses->reasonId(RequestSubStatusCode::ReadyForRelease),
        );
    }

    private function record(Program $program, User $actor, ProgramApprovalAction $action, ?string $remark): void
    {
        ProgramApprovalEvent::query()->create([
            'program_id' => $program->id,
            'user_id' => $actor->id,
            'action' => $action,
            'remark' => $remark,
        ]);
    }
}
