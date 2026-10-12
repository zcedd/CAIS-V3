<?php

namespace App\Http\Requests\User\Distribution;

use App\Models\DistributionBatch;
use Illuminate\Foundation\Http\FormRequest;

class AssignDistributionPersonnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('distributionBatch');

        return $batch instanceof DistributionBatch
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
