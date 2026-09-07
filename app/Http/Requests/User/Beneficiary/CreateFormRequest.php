<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Http\Requests\User\Beneficiary\Concerns\AuthorizesDepartmentBeneficiary;
use Illuminate\Foundation\Http\FormRequest;

class CreateFormRequest extends FormRequest
{
    use AuthorizesDepartmentBeneficiary;

    public function authorize(): bool
    {
        return $this->canCreateBeneficiary();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
