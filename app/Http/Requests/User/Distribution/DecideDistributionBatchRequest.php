<?php

namespace App\Http\Requests\User\Distribution;

use App\Enums\DistributionDecisionAction;
use App\Models\DistributionBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideDistributionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('distributionBatch');

        if (! $batch instanceof DistributionBatch || $this->user() === null) {
            return false;
        }

        return match ($this->input('decision')) {
            DistributionDecisionAction::Approved->value => $this->user()->can('approve', $batch),
            DistributionDecisionAction::Returned->value => $this->user()->can('returnBatch', $batch),
            DistributionDecisionAction::Rejected->value => $this->user()->can('reject', $batch),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([
                DistributionDecisionAction::Approved->value,
                DistributionDecisionAction::Returned->value,
                DistributionDecisionAction::Rejected->value,
            ])],
            'remarks' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
        ];
    }
}
