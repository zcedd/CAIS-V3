<?php

namespace App\Http\Requests\User;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;

class SearchBeneficiariesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        if (! $department instanceof Department) {
            return $this->user() !== null;
        }

        return $this->user()?->department_id === $department->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'beneficiary_type' => ['nullable', 'in:individual,organization'],
        ];
    }

    public function search(): string
    {
        return trim($this->string('q')->toString());
    }

    public function beneficiaryType(): ?string
    {
        $type = $this->string('beneficiary_type')->toString();

        return $type === '' ? null : $type;
    }
}
