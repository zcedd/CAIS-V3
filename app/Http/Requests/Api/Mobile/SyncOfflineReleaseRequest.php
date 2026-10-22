<?php

namespace App\Http\Requests\Api\Mobile;

use App\Http\Requests\User\Distribution\FinalizeDistributionTransactionRequest;
use App\Models\DistributionBatch;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SyncOfflineReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('distributionBatch');
        $user = $this->user();

        if (! $batch instanceof DistributionBatch || ! $user instanceof User) {
            return false;
        }

        if ((int) $batch->department_id !== (int) $user->department_id) {
            abort(404);
        }

        return $user->can('issueReceipt', $batch);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_operation_id' => ['required', 'uuid'],
            'idempotency_key' => ['required', 'uuid'],
            'captured_at' => ['required', 'date'],
            'live_photo' => ['nullable', 'file', 'max:'.(int) config('distribution.live_photo_max_kilobytes', 5120)],
            'signature' => ['nullable', 'file', 'max:'.(int) config('distribution.signature_max_kilobytes', 2048)],
            ...(new FinalizeDistributionTransactionRequest)->rules(),
        ];
    }
}
