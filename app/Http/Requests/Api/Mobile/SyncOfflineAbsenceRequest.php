<?php

namespace App\Http\Requests\Api\Mobile;

use App\Models\DistributionBatch;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SyncOfflineAbsenceRequest extends FormRequest
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

        return $user->can('execute', $batch);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_operation_id' => ['required', 'uuid'],
            'remarks' => ['required', 'string', 'max:2000'],
            'captured_at' => ['required', 'date'],
        ];
    }
}
