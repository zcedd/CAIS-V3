<?php

namespace App\Http\Requests\User\Distribution;

use App\Models\DistributionBatch;
use Illuminate\Foundation\Http\FormRequest;

class VoidDistributionTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('distributionBatch');

        return $batch instanceof DistributionBatch
            && ($this->user()?->can('voidTransaction', $batch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
