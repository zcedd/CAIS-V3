<?php

namespace App\Http\Requests\User\Disbursement;

use App\Models\DisbursementBatch;
use Illuminate\Foundation\Http\FormRequest;

class AssignDisbursementPersonnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('disbursementBatch');

        return $batch instanceof DisbursementBatch
            && ($this->user()?->can('assignPersonnel', $batch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer'],
        ];
    }
}
