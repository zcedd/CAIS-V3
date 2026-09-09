<?php

namespace App\Services\User;

use App\Models\Assistance;
use App\Models\Department;
use App\Models\User;
use App\Support\EmptyCell;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class AssistanceQueueService
{
    /**
     * @param  array{
     *     tab?: string,
     *     search?: string,
     *     program?: list<int>|int|null,
     *     status?: list<string>|string|null,
     *     sla?: list<string>|string|null,
     *     include_assigned?: bool
     * }  $filters
     */
    public function paginate(Department $department, User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $tab = $filters['tab'] === 'team' ? 'team' : 'mine';
        $search = trim((string) ($filters['search'] ?? ''));
        $programIds = $this->listOfInts($filters['program'] ?? null);
        $statuses = $this->listOfStrings($filters['status'] ?? null);
        $slaStates = $this->listOfStrings($filters['sla'] ?? null);
        $includeAssigned = (bool) ($filters['include_assigned'] ?? false);

        $query = Assistance::query()
            ->select('assistances.*')
            ->join('programs', 'programs.id', '=', 'assistances.program_id')
            ->where('programs.department_id', $department->id)
            ->whereNull('programs.deleted_at')
            ->open()
            ->with([
                'beneficiary:id,name,cais_number',
                'program:id,name,department_id,workflow_id,parent_id',
                'currentRequestSubStatus:id,name,request_status_id,code',
                'currentRequestSubStatus.requestStatus:id,name,code',
                'assignedTo:id,firstName,lastName',
                'user:id,firstName,lastName',
                'program.workflow.steps',
                'program.parent.workflow.steps',
            ]);

        if ($tab === 'mine') {
            $query->assignedToUser($user);
        } elseif (! $includeAssigned) {
            $query->unassigned();
        }

        if ($programIds !== []) {
            $query->whereIn('assistances.program_id', $programIds);
        }

        if ($statuses !== []) {
            $query->whereHas('currentRequestSubStatus.requestStatus', function ($statusQuery) use ($statuses): void {
                $statusQuery->whereIn('request_statuses.name', $statuses)
                    ->orWhereIn('request_statuses.code', $statuses);
            });
        }

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->whereHas('beneficiary', function ($beneficiaryQuery) use ($search): void {
                        $beneficiaryQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('cais_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('program', function ($programQuery) use ($search): void {
                        $programQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $query
            ->orderByRaw('assistances.sla_due_at is null')
            ->orderBy('assistances.sla_due_at')
            ->orderBy('assistances.current_status_recorded_at');

        $paginator = $query->paginate($perPage)->withQueryString();

        if ($slaStates !== []) {
            $paginator->setCollection(
                $paginator->getCollection()->filter(
                    fn (Assistance $assistance): bool => in_array($assistance->slaState(), $slaStates, true),
                )->values(),
            );
        }

        return $paginator->through(fn (Assistance $assistance): array => $this->serialize($assistance, $user));
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Assistance $assistance, ?User $viewer = null): array
    {
        $assignee = $assistance->assignedTo;

        return [
            'id' => $assistance->id,
            'program_id' => $assistance->program_id,
            'program_name' => $assistance->program?->name ?? EmptyCell::VALUE,
            'cais_number' => $assistance->beneficiary?->cais_number ?? EmptyCell::VALUE,
            'beneficiary_name' => $assistance->beneficiary?->name ?? EmptyCell::VALUE,
            'request_status' => $assistance->currentRequestSubStatus?->requestStatus?->name,
            'request_sub_status' => $assistance->currentRequestSubStatus?->name,
            'assigned_to_id' => $assistance->assigned_to_id,
            'assignee_name' => $assignee instanceof User
                ? trim($assignee->firstName.' '.$assignee->lastName)
                : null,
            'encoder_name' => $assistance->user_id === null
                ? 'Public intake'
                : trim(($assistance->user?->firstName ?? '').' '.($assistance->user?->lastName ?? '')),
            'sla_due_at' => $assistance->sla_due_at?->toIso8601String(),
            'sla_state' => $assistance->slaState(),
            'sla_label' => $assistance->slaState()->label(),
            'current_status_recorded_at' => $assistance->current_status_recorded_at instanceof \DateTimeInterface
                ? Carbon::parse($assistance->current_status_recorded_at)->toIso8601String()
                : null,
            'can_advance' => $viewer instanceof User && $viewer->can('advance', $assistance),
            'can_claim' => $viewer instanceof User && $viewer->can('claim', $assistance),
            'step_has_owner' => $assistance->currentWorkflowStep()?->assigned_to_id !== null,
        ];
    }

    /**
     * @return list<int>
     */
    private function listOfInts(mixed $value): array
    {
        $items = is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]);

        return array_values(array_filter(
            array_map(static fn (mixed $item): int => (int) $item, $items),
            static fn (int $item): bool => $item > 0,
        ));
    }

    /**
     * @return list<string>
     */
    private function listOfStrings(mixed $value): array
    {
        $items = is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]);

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $items),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
