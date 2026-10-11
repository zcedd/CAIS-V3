<?php

namespace App\Http\Requests\User\Dispersal;

use App\Enums\DispersalDecisionAction;
use App\Models\DispersalBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideDispersalBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('dispersalBatch');

        if (! $batch instanceof DispersalBatch || $this->user() === null) {
            return false;
        }

        return match ($this->input('decision')) {
            DispersalDecisionAction::Approved->value => $this->user()->can('approve', $batch),
            DispersalDecisionAction::Returned->value => $this->user()->can('returnBatch', $batch),
            DispersalDecisionAction::Rejected->value => $this->user()->can('reject', $batch),
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
                DispersalDecisionAction::Approved->value,
                DispersalDecisionAction::Returned->value,
                DispersalDecisionAction::Rejected->value,
            ])],
            'remarks' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
        ];
    }
}
