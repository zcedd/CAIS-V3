<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Http\Requests\User\Beneficiary\Concerns\AuthorizesDepartmentBeneficiary;
use Illuminate\Foundation\Http\FormRequest;

class ShowRequest extends FormRequest
{
    use AuthorizesDepartmentBeneficiary;

    public function authorize(): bool
    {
        return $this->canViewBeneficiary();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function search(): string
    {
        return trim($this->validated('search') ?? '');
    }
}
