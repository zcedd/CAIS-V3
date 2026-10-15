<?php

namespace App\Services\User;

use App\Enums\ProgramApprovalAction;
use App\Enums\ProgramApprovalStatus;
use App\Models\Department;
use App\Models\Program;
use App\Models\ProgramApprovalEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as ProgramPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProgramApprovalService
{
    public function submit(Program $program, User $actor, ?string $remark): void
    {
        if ($program->isScheme()) {
            throw ValidationException::withMessages([
                'program' => 'Submit each batch separately for approval.',
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

        DB::transaction(function () use ($program, $actor, $remark): void {
            $program->update([
                'approval_status' => ProgramApprovalStatus::AwaitingAdministrator,
            ]);
            $this->record($program, $actor, ProgramApprovalAction::Submitted, $remark);
        });
    }

    public function endorse(Program $program, User $actor, ?string $remark): void
    {
        $this->assertAwaitingAdministrator($program);

        DB::transaction(function () use ($program, $actor, $remark): void {
            $program->update([
                'approval_status' => ProgramApprovalStatus::AwaitingGovernor,
            ]);
            $this->record($program, $actor, ProgramApprovalAction::Endorsed, $remark);
        });
    }

    public function approve(Program $program, User $actor, ?string $remark): void
    {
        $this->assertAwaitingGovernor($program);

        DB::transaction(function () use ($program, $actor, $remark): void {
            $program->update([
                'approval_status' => ProgramApprovalStatus::Approved,
            ]);
            $this->record($program, $actor, ProgramApprovalAction::Approved, $remark);
        });
    }

    public function returnToDepartment(Program $program, User $actor, ?string $remark): void
    {
        $this->assertReturnable($program);

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

    public function awaitingAdministratorCount(): int
    {
        return Program::query()
            ->where('approval_status', ProgramApprovalStatus::AwaitingAdministrator->value)
            ->count();
    }

    public function awaitingGovernorCount(): int
    {
        return Program::query()
            ->where('approval_status', ProgramApprovalStatus::AwaitingGovernor->value)
            ->count();
    }

    /**
     * Counts ignore the approval filter so the status pills stay stable.
     *
     * @param  list<string>  $types
     * @param  list<string>  $statuses
     * @param  list<int>  $departmentIds
     * @return array{all: int, draft: int, awaiting_administrator: int, awaiting_governor: int, approved: int, returned: int}
     */
    public function approvalCounts(
        string $search,
        array $types,
        array $statuses,
        array $departmentIds,
    ): array {
        $base = Program::query()
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
                $departmentIds !== [],
                fn (Builder $query) => $query->whereIn('department_id', $departmentIds),
            );

        $rows = (clone $base)
            ->selectRaw('approval_status, COUNT(*) as aggregate')
            ->groupBy('approval_status')
            ->pluck('aggregate', 'approval_status');

        return [
            'all' => (int) (clone $base)->count(),
            'draft' => (int) ($rows[ProgramApprovalStatus::Draft->value] ?? 0),
            'awaiting_administrator' => (int) ($rows[ProgramApprovalStatus::AwaitingAdministrator->value] ?? 0),
            'awaiting_governor' => (int) ($rows[ProgramApprovalStatus::AwaitingGovernor->value] ?? 0),
            'approved' => (int) ($rows[ProgramApprovalStatus::Approved->value] ?? 0),
            'returned' => (int) ($rows[ProgramApprovalStatus::Returned->value] ?? 0),
        ];
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
                    'kind' => $program->kind?->value,
                    'batches_count' => (int) $program->batches_count,
                    'open_batches_count' => (int) $program->open_batches_count,
                    'approval_status' => $status->value,
                    'approval_label' => $status->label(),
                    'can_approve' => Gate::allows('approve', $program),
                    'can_endorse' => Gate::allows('endorse', $program),
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
            'department' => $program->department === null ? null : [
                'id' => $program->department->id,
                'name' => $program->department->name,
            ],
        ];
    }

    /**
     * @param  ProgramPaginator<int, array<string, mixed>>  $programs
     * @return ProgramPaginator<int, array<string, mixed>>
     */
    public function withDecisionUrls(
        ProgramPaginator $programs,
        string $showRoute,
        string $positiveRoute,
        string $returnRoute,
    ): ProgramPaginator {
        $programs->setCollection(
            $programs->getCollection()->map(function (array $program) use ($showRoute, $positiveRoute, $returnRoute): array {
                $id = $program['id'];
                $program['show_url'] = route($showRoute, $id);
                $program['positive_url'] = route($positiveRoute, $id);
                $program['return_url'] = route($returnRoute, $id);

                return $program;
            }),
        );

        return $programs;
    }

    private function assertAwaitingAdministrator(Program $program): void
    {
        if ($program->approval_status !== ProgramApprovalStatus::AwaitingAdministrator) {
            throw ValidationException::withMessages([
                'program' => 'This program is not awaiting administrator endorsement.',
            ]);
        }
    }

    private function assertAwaitingGovernor(Program $program): void
    {
        if ($program->approval_status !== ProgramApprovalStatus::AwaitingGovernor) {
            throw ValidationException::withMessages([
                'program' => 'This program is not awaiting executive approval.',
            ]);
        }
    }

    private function assertReturnable(Program $program): void
    {
        if (! in_array($program->approval_status, [
            ProgramApprovalStatus::AwaitingAdministrator,
            ProgramApprovalStatus::AwaitingGovernor,
        ], true)) {
            throw ValidationException::withMessages([
                'program' => 'This program is not awaiting a decision.',
            ]);
        }
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
