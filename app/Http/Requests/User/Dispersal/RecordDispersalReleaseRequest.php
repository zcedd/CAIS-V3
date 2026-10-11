<?php

namespace App\Http\Requests\User\Dispersal;

use App\Enums\DispersalReleaseOutcome;
use App\Models\DispersalBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordDispersalReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('dispersalBatch');

        return $batch instanceof DispersalBatch
            && ($this->user()?->can('execute', $batch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dispersal_batch_item_id' => ['required', 'integer'],
            'dispersal_batch_item_line_id' => ['nullable', 'integer'],
            'outcome' => ['required', Rule::enum(DispersalReleaseOutcome::class)],
            'actual_quantity' => ['nullable', 'numeric', 'min:0'],
            'actual_amount' => ['nullable', 'numeric', 'min:0'],
            'receipt_reference' => ['nullable', 'string', 'max:255'],
            'identity_verified' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string'],
            'released_at' => ['nullable', 'date'],
        ];
    }
}
