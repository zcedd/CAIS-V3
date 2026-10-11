<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Dispersal\SaveDispersalApprovalPolicy;
use App\Enums\DispersalApprovalMode;
use App\Enums\DispersalApprovalStrategy;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dispersal\SaveDispersalApprovalPolicyRequest;
use App\Models\Department;
use App\Models\DispersalApprovalPolicy;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DispersalApprovalPolicyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/dispersal-policies/index', [
            'policies' => DispersalApprovalPolicy::query()
                ->with(['department:id,name', 'program:id,name', 'stages'])
                ->latest('id')
                ->get()
                ->map(fn (DispersalApprovalPolicy $policy): array => [
                    'id' => $policy->id,
                    'name' => $policy->name,
                    'department_id' => $policy->department_id,
                    'program_id' => $policy->program_id,
                    'department' => $policy->department?->name,
                    'program' => $policy->program?->name,
                    'approval_mode' => $policy->approval_mode?->value,
                    'strategy' => $policy->strategy->value,
                    'is_active' => $policy->is_active,
                    'allow_partial_finalization' => $policy->allow_partial_finalization,
                    'allow_amount_modification' => $policy->allow_amount_modification,
                    'allow_self_approval' => $policy->allow_self_approval,
                    'reschedule_requires_reapproval' => $policy->reschedule_requires_reapproval,
                    'allow_resubmit_after_reject' => $policy->allow_resubmit_after_reject,
                    'block_active_batch_reuse' => $policy->block_active_batch_reuse,
                    'min_amount' => $policy->min_amount,
                    'max_amount' => $policy->max_amount,
                    'stages' => $policy->stages->map(fn ($stage): array => [
                        'name' => $stage->name,
                        'sequence' => $stage->sequence,
                        'role_name' => $stage->role_name,
                        'user_id' => $stage->user_id,
                    ])->all(),
                ]),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'programs' => Program::query()->encodable()->orderBy('name')->get(['id', 'name', 'department_id']),
            'modes' => collect(DispersalApprovalMode::cases())->map(fn (DispersalApprovalMode $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ])->all(),
            'strategies' => collect(DispersalApprovalStrategy::cases())->map(fn (DispersalApprovalStrategy $strategy): array => [
                'value' => $strategy->value,
                'label' => $strategy->label(),
            ])->all(),
            'roles' => collect(RoleName::cases())->map(fn (RoleName $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->all(),
        ]);
    }

    public function store(SaveDispersalApprovalPolicyRequest $request, SaveDispersalApprovalPolicy $save): RedirectResponse
    {
        $save($request->user(), $request->validated());

        return back()->with('success', 'Approval policy saved.');
    }

    public function update(
        SaveDispersalApprovalPolicyRequest $request,
        DispersalApprovalPolicy $policy,
        SaveDispersalApprovalPolicy $save,
    ): RedirectResponse {
        $save($request->user(), $request->validated(), $policy);

        return back()->with('success', 'Approval policy updated.');
    }
}
