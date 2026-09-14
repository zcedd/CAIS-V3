<?php

namespace App\Http\Requests\User\Beneficiary\Concerns;

use App\Models\Beneficiary;
use App\Models\Department;

trait AuthorizesDepartmentBeneficiary
{
    protected function userBelongsToDepartment(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && $this->user()?->department_id === $department->id;
    }

    protected function canViewAnyBeneficiaries(): bool
    {
        return $this->userBelongsToDepartment();
    }

    protected function canCreateBeneficiary(): bool
    {
        return $this->userBelongsToDepartment();
    }

    protected function canViewBeneficiary(): bool
    {
        $beneficiary = $this->route('beneficiary');

        return $beneficiary instanceof Beneficiary
            && $this->userBelongsToDepartment();
    }

    protected function canUpdateBeneficiary(): bool
    {
        $beneficiary = $this->route('beneficiary');

        return $beneficiary instanceof Beneficiary
            && $this->userBelongsToDepartment();
    }
}
