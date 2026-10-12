<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Distribution\SaveDistributionApprovalPolicy;
use App\Enums\DistributionApprovalMode;
use App\Enums\DistributionApprovalStrategy;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Distribution\SaveDistributionApprovalPolicyRequest;
use App\Models\Department;
use App\Models\DistributionApprovalPolicy;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DistributionApprovalPolicyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/distribution-policies/index', [
            'policies' => DistributionApprovalPolicy::query()
                ->with(['department:id,name', 'program:id,name', 'stages'])
                ->latest('id')
                ->get()
                ->map(fn (DistributionApprovalPolicy $policy): array => [
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
            'modes' => collect(DistributionApprovalMode::cases())->map(fn (DistributionApprovalMode $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ])->all(),
            'strategies' => collect(DistributionApprovalStrategy::cases())->map(fn (DistributionApprovalStrategy $strategy): array => [
                'value' => $strategy->value,
                'label' => $strategy->label(),
            ])->all(),
            'roles' => collect(RoleName::cases())->map(fn (RoleName $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->all(),
        ]);
    }

    public function store(SaveDistributionApprovalPolicyRequest $request, SaveDistributionApprovalPolicy $save): RedirectResponse
    {
        $save($request->user(), $request->validated());

        return back()->with('success', 'Approval policy saved.');
    }

    public function update(
        SaveDistributionApprovalPolicyRequest $request,
        DistributionApprovalPolicy $policy,
        SaveDistributionApprovalPolicy $save,
    ): RedirectResponse {
        $save($request->user(), $request->validated(), $policy);

        return back()->with('success', 'Approval policy updated.');
    }
}
