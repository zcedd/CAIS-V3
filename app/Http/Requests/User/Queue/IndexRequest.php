<?php

namespace App\Http\Requests\User\Queue;

use App\Models\Assistance;
use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && Gate::allows('viewAny', [Assistance::class, $department]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', 'in:mine,team'],
            'search' => ['nullable', 'string', 'max:255'],
            'program' => ['nullable', 'array'],
            'program.*' => ['integer'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string'],
            'sla' => ['nullable', 'array'],
            'sla.*' => ['string'],
            'include_assigned' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }
}
