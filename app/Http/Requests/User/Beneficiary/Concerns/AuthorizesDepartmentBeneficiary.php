<?php

namespace App\Http\Requests\User\Beneficiary\Concerns;

use App\Models\Beneficiary;
use App\Models\Department;
use Illuminate\Support\Facades\Gate;

trait AuthorizesDepartmentBeneficiary
{
    protected function canViewAnyBeneficiaries(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && Gate::allows('viewAny', [Beneficiary::class, $department]);
    }

    protected function canCreateBeneficiary(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && Gate::allows('create', [Beneficiary::class, $department]);
    }

    protected function canViewBeneficiary(): bool
    {
        $beneficiary = $this->route('beneficiary');

        return $beneficiary instanceof Beneficiary
            && Gate::allows('view', $beneficiary);
    }

    protected function canUpdateBeneficiary(): bool
    {
        $beneficiary = $this->route('beneficiary');

        return $beneficiary instanceof Beneficiary
            && Gate::allows('update', $beneficiary);
    }
}
