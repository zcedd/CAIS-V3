<?php

namespace App\Http\Requests\User\Distribution;

use App\Models\DistributionBatch;
use Illuminate\Foundation\Http\FormRequest;

class StartDistributionTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('distributionBatch');

        return $batch instanceof DistributionBatch
            && ($this->user()?->can('execute', $batch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
