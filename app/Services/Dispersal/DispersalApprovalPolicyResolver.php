<?php

namespace App\Services\Dispersal;

use App\Enums\DispersalApprovalMode;
use App\Enums\DispersalApprovalStrategy;
use App\Enums\RoleName;
use App\Models\DispersalApprovalPolicy;
use App\Models\Program;
use App\Support\DecimalAmount;
use Illuminate\Support\Collection;

class DispersalApprovalPolicyResolver
{
    public function resolve(Program $program, DispersalApprovalMode $mode, string $amount): DispersalApprovalPolicy
    {
        $match = $this->candidates($program, $mode, $amount)->first();

        if ($match instanceof DispersalApprovalPolicy) {
            return $match->load('stages');
        }

        return $this->ensureDepartmentDefault((int) $program->department_id);
    }

    /**
     * @return Collection<int, DispersalApprovalPolicy>
     */
    private function candidates(Program $program, DispersalApprovalMode $mode, string $amount): Collection
    {
        return DispersalApprovalPolicy::query()
            ->with('stages')
            ->where('is_active', true)
            ->where(function ($query) use ($program): void {
                $query->whereNull('department_id')
                    ->orWhere('department_id', $program->department_id);
            })
            ->where(function ($query) use ($program): void {
                $query->whereNull('program_id')
                    ->orWhere('program_id', $program->id);
            })
            ->where(function ($query) use ($mode): void {
                $query->whereNull('approval_mode')
                    ->orWhere('approval_mode', $mode->value);
            })
            ->get()
            ->filter(function (DispersalApprovalPolicy $policy) use ($amount): bool {
                if ($policy->min_amount !== null && DecimalAmount::comp($amount, $policy->min_amount) < 0) {
                    return false;
                }

                if ($policy->max_amount !== null && DecimalAmount::comp($amount, $policy->max_amount) > 0) {
                    return false;
                }

                return true;
            })
            ->sort(function (DispersalApprovalPolicy $left, DispersalApprovalPolicy $right): int {
                $score = $this->score($right) <=> $this->score($left);

                if ($score !== 0) {
                    return $score;
                }

                return $right->id <=> $left->id;
            })
            ->values();
    }

    private function score(DispersalApprovalPolicy $policy): int
    {
        $score = 0;

        if ($policy->program_id !== null) {
            $score += 100;
        }

        if ($policy->department_id !== null) {
            $score += 40;
        }

        if ($policy->approval_mode !== null) {
            $score += 20;
        }

        if ($policy->min_amount !== null || $policy->max_amount !== null) {
            $score += 10;
        }

        return $score;
    }

    private function ensureDepartmentDefault(int $departmentId): DispersalApprovalPolicy
    {
        $existing = DispersalApprovalPolicy::query()
            ->where('department_id', $departmentId)
            ->whereNull('program_id')
            ->whereNull('approval_mode')
            ->where('name', 'Default executive approval')
            ->where('is_active', true)
            ->first();

        if ($existing instanceof DispersalApprovalPolicy) {
            return $existing->load('stages');
        }

        $policy = DispersalApprovalPolicy::query()->create([
            'name' => 'Default executive approval',
            'department_id' => $departmentId,
            'strategy' => DispersalApprovalStrategy::Sequential,
            'allow_partial_finalization' => false,
            'allow_amount_modification' => false,
            'allow_self_approval' => false,
            'reschedule_requires_reapproval' => false,
            'allow_resubmit_after_reject' => false,
            'block_active_batch_reuse' => true,
            'is_active' => true,
        ]);

        $policy->stages()->create([
            'sequence' => 1,
            'name' => 'Executive approval',
            'role_name' => RoleName::Governor->value,
        ]);

        return $policy->load('stages');
    }
}
