<?php

namespace App\Http\Requests\User\Disbursement;

use App\Models\DisbursementBatch;
use Illuminate\Foundation\Http\FormRequest;

class StartDisbursementTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('disbursementBatch');

        return $batch instanceof DisbursementBatch
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
