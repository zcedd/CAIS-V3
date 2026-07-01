<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Individual;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIndividualRequest extends FormRequest
{
    public function authorize(): bool
    {
        $beneficiary = $this->route('beneficiary');

        if (! $beneficiary instanceof Beneficiary) {
            return false;
        }

        if ($beneficiary->beneficiable_type !== Individual::class) {
            return false;
        }

        return $this->userBelongsToDepartment();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return (new StoreIndividualRequest)->rules();
    }

    protected function userBelongsToDepartment(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && $this->user()?->department_id === $department->id;
    }
}
