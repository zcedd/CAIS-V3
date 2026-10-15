<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionApprovalStrategy;
use App\Models\DistributionApprovalPolicy;
use App\Models\DistributionApprovalStage;
use App\Models\DistributionApprovalTask;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveDistributionApprovalPolicy
{
    /**
     * @param  array{
     *     name: string,
     *     department_id?: int|null,
     *     program_id?: int|null,
     *     approval_mode?: string|null,
     *     strategy: string,
     *     allow_partial_finalization?: bool,
     *     allow_amount_modification?: bool,
     *     allow_self_approval?: bool,
     *     reschedule_requires_reapproval?: bool,
     *     allow_resubmit_after_reject?: bool,
     *     block_active_batch_reuse?: bool,
     *     min_amount?: string|null,
     *     max_amount?: string|null,
     *     is_active?: bool,
     *     stages: list<array{id?: int|null, name: string, sequence: int, role_name?: string|null, user_id?: int|null}>
     * }  $data
     */
    public function __invoke(User $actor, array $data, ?DistributionApprovalPolicy $policy = null): DistributionApprovalPolicy
    {
        return DB::transaction(function () use ($actor, $data, $policy): DistributionApprovalPolicy {
            if ($data['stages'] === []) {
                throw ValidationException::withMessages([
                    'stages' => 'Add at least one approval stage.',
                ]);
            }

            foreach ($data['stages'] as $stage) {
                $hasRole = isset($stage['role_name']) && $stage['role_name'] !== null && $stage['role_name'] !== '';
                $hasUser = isset($stage['user_id']) && $stage['user_id'] !== null;

                if (! $hasRole && ! $hasUser) {
                    throw ValidationException::withMessages([
                        'stages' => 'Each stage needs a role or a specific user.',
                    ]);
                }
            }

            $attributes = [
                'name' => $data['name'],
                'department_id' => $data['department_id'] ?? null,
                'program_id' => $data['program_id'] ?? null,
                'approval_mode' => $data['approval_mode'] ?? null,
                'strategy' => DistributionApprovalStrategy::from($data['strategy']),
                'allow_partial_finalization' => (bool) ($data['allow_partial_finalization'] ?? false),
                'allow_amount_modification' => (bool) ($data['allow_amount_modification'] ?? false),
                'allow_self_approval' => (bool) ($data['allow_self_approval'] ?? false),
                'reschedule_requires_reapproval' => (bool) ($data['reschedule_requires_reapproval'] ?? false),
                'allow_resubmit_after_reject' => (bool) ($data['allow_resubmit_after_reject'] ?? false),
                'block_active_batch_reuse' => (bool) ($data['block_active_batch_reuse'] ?? true),
                'min_amount' => $data['min_amount'] ?? null,
                'max_amount' => $data['max_amount'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $policy?->created_by ?? $actor->id,
            ];

            if ($policy instanceof DistributionApprovalPolicy) {
                $policy->fill($attributes)->save();
            } else {
                $policy = DistributionApprovalPolicy::query()->create($attributes);
            }

            $this->syncStages($policy, $data['stages']);

            return $policy->load('stages');
        });
    }

    /**
     * @param  list<array{id?: int|null, name: string, sequence: int, role_name?: string|null, user_id?: int|null}>  $stages
     */
    private function syncStages(DistributionApprovalPolicy $policy, array $stages): void
    {
        /** @var Collection<int, DistributionApprovalStage> $existing */
        $existing = $policy->stages()->get()->keyBy('id');
        /** @var Collection<int, array{name: string, sequence: int, role_name: string|null, user_id: int|null}> $kept */
        $kept = collect();
        $creates = [];

        foreach ($stages as $stage) {
            $attributes = [
                'name' => $stage['name'],
                'sequence' => $stage['sequence'],
                'role_name' => $stage['role_name'] ?? null,
                'user_id' => $stage['user_id'] ?? null,
            ];
            $id = isset($stage['id']) ? (int) $stage['id'] : null;

            if ($id === null || $id === 0) {
                $creates[] = $attributes;

                continue;
            }

            if (! $existing->has($id)) {
                throw ValidationException::withMessages([
                    'stages' => 'One of the stages does not belong to this policy.',
                ]);
            }

            if ($kept->has($id)) {
                throw ValidationException::withMessages([
                    'stages' => 'Each stage can only be saved once.',
                ]);
            }

            $kept->put($id, $attributes);
        }

        $removed = $existing->reject(
            fn (DistributionApprovalStage $stage): bool => $kept->has($stage->id),
        );

        if ($removed->isNotEmpty() && DistributionApprovalTask::query()
            ->whereIn('distribution_approval_stage_id', $removed->modelKeys())
            ->exists()) {
            throw ValidationException::withMessages([
                'stages' => 'A stage that already has approval tasks cannot be removed.',
            ]);
        }

        foreach ($existing as $stage) {
            $stage->update(['sequence' => $stage->sequence + 30000]);
        }

        foreach ($kept as $id => $attributes) {
            $existing->get($id)?->update($attributes);
        }

        foreach ($creates as $attributes) {
            $policy->stages()->create($attributes);
        }

        if ($removed->isNotEmpty()) {
            $policy->stages()->whereKey($removed->modelKeys())->delete();
        }
    }
}
