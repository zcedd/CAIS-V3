<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Http\Requests\User\Beneficiary\Concerns\AuthorizesDepartmentBeneficiary;
use Illuminate\Foundation\Http\FormRequest;

class EditRequest extends FormRequest
{
    use AuthorizesDepartmentBeneficiary;

    public function authorize(): bool
    {
        return $this->canUpdateBeneficiary();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
