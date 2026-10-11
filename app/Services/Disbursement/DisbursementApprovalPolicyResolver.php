<?php

namespace App\Services\Disbursement;

use App\Enums\DisbursementApprovalMode;
use App\Enums\DisbursementApprovalStrategy;
use App\Enums\RoleName;
use App\Models\DisbursementApprovalPolicy;
use App\Models\Program;
use App\Support\DecimalAmount;
use Illuminate\Support\Collection;

class DisbursementApprovalPolicyResolver
{
    public function resolve(Program $program, DisbursementApprovalMode $mode, string $amount): DisbursementApprovalPolicy
    {
        $match = $this->candidates($program, $mode, $amount)->first();

        if ($match instanceof DisbursementApprovalPolicy) {
            return $match->load('stages');
        }

        return $this->ensureDepartmentDefault((int) $program->department_id);
    }

    /**
     * @return Collection<int, DisbursementApprovalPolicy>
     */
    private function candidates(Program $program, DisbursementApprovalMode $mode, string $amount): Collection
    {
        return DisbursementApprovalPolicy::query()
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
            ->filter(function (DisbursementApprovalPolicy $policy) use ($amount): bool {
                if ($policy->min_amount !== null && DecimalAmount::comp($amount, $policy->min_amount) < 0) {
                    return false;
                }

                if ($policy->max_amount !== null && DecimalAmount::comp($amount, $policy->max_amount) > 0) {
                    return false;
                }

                return true;
            })
            ->sort(function (DisbursementApprovalPolicy $left, DisbursementApprovalPolicy $right): int {
                $score = $this->score($right) <=> $this->score($left);

                if ($score !== 0) {
                    return $score;
                }

                return $right->id <=> $left->id;
            })
            ->values();
    }

    private function score(DisbursementApprovalPolicy $policy): int
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

    private function ensureDepartmentDefault(int $departmentId): DisbursementApprovalPolicy
    {
        $existing = DisbursementApprovalPolicy::query()
            ->where('department_id', $departmentId)
            ->whereNull('program_id')
            ->whereNull('approval_mode')
            ->where('name', 'Default executive approval')
            ->where('is_active', true)
            ->first();

        if ($existing instanceof DisbursementApprovalPolicy) {
            return $existing->load('stages');
        }

        $policy = DisbursementApprovalPolicy::query()->create([
            'name' => 'Default executive approval',
            'department_id' => $departmentId,
            'strategy' => DisbursementApprovalStrategy::Sequential,
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
            'role_name' => RoleName::Executive->value,
        ]);

        return $policy->load('stages');
    }
}
