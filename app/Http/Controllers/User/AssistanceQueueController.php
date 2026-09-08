<?php

namespace App\Http\Controllers\User;

use App\Actions\User\AssignAssistance;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Assistance\BulkAssignRequest;
use App\Http\Requests\User\Queue\IndexRequest;
use App\Models\Department;
use App\Models\Program;
use App\Models\RequestStatus;
use App\Models\User;
use App\Services\User\AssistanceQueueService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssistanceQueueController extends Controller
{
    public function __construct(
        private AssistanceQueueService $assistanceQueueService,
    ) {}

    public function index(IndexRequest $request, Department $department): Response
    {
        $tab = $request->input('tab', 'mine');
        $perPage = (int) ($request->input('per_page') ?: 25);

        return Inertia::render('user/queue/index', [
            'department' => $department->only(['id', 'name', 'slug']),
            'tab' => $tab === 'team' ? 'team' : 'mine',
            'search' => (string) $request->input('search', ''),
            'program' => $request->input('program', []),
            'status' => $request->input('status', []),
            'sla' => $request->input('sla', []),
            'include_assigned' => $request->boolean('include_assigned'),
            'assistances' => $this->assistanceQueueService->paginate(
                $department,
                $request->user(),
                $request->validated() + ['tab' => $tab],
                $perPage,
            ),
            'program_options' => Program::query()
                ->where('department_id', $department->id)
                ->encodable()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(static fn (Program $program): array => [
                    'label' => $program->name,
                    'value' => (string) $program->id,
                ])
                ->all(),
            'status_options' => RequestStatus::query()
                ->active()
                ->orderBy('sort_order')
                ->get(['name'])
                ->map(static fn (RequestStatus $status): array => [
                    'label' => $status->name,
                    'value' => $status->name,
                ])
                ->all(),
            'staff_options' => User::query()
                ->where('department_id', $department->id)
                ->orderBy('lastName')
                ->orderBy('firstName')
                ->get(['id', 'firstName', 'lastName'])
                ->map(static fn (User $user): array => [
                    'id' => $user->id,
                    'name' => trim($user->firstName.' '.$user->lastName),
                ])
                ->all(),
        ]);
    }

    public function bulkAssign(
        BulkAssignRequest $request,
        Department $department,
        AssignAssistance $assignAssistance,
    ): RedirectResponse {
        $assignee = $request->assignee();
        $actor = $request->user();
        $count = 0;

        foreach ($request->assistances() as $assistance) {
            $assignAssistance($assistance, $actor, $assignee, $request->validated('remark'));
            $count++;
        }

        return redirect()
            ->back()
            ->with('success', $count === 1
                ? 'Assistance assignment updated.'
                : "Updated assignment on {$count} assistance requests.");
    }
}
