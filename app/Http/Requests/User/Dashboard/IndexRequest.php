<?php

namespace App\Http\Requests\User\Dashboard;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->userBelongsToDepartment();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $department = $this->route('department');
        $departmentId = $department instanceof Department ? $department->id : null;

        return [
            'program' => ['nullable', 'array'],
            'program.*' => [
                'integer',
                Rule::exists('programs', 'id')->where(
                    fn ($query) => $query->where('department_id', $departmentId),
                ),
            ],
            'beneficiary_type' => ['nullable', 'array'],
            'beneficiary_type.*' => ['string', Rule::in(['individual', 'organization'])],
            'sex' => ['nullable', 'array'],
            'sex.*' => ['string', Rule::in(['Male', 'Female'])],
            'pwd' => ['nullable', 'array'],
            'pwd.*' => ['string', Rule::in(['true', 'false'])],
            'four_ps' => ['nullable', 'array'],
            'four_ps.*' => ['string', Rule::in(['true', 'false'])],
            'solo_parent' => ['nullable', 'array'],
            'solo_parent.*' => ['string', Rule::in(['true', 'false'])],
            'indigenous' => ['nullable', 'array'],
            'indigenous.*' => ['string', Rule::in(['true', 'false'])],
        ];
    }

    /**
     * @return array{
     *     program: list<int>,
     *     beneficiary_type: list<string>,
     *     sex: list<string>,
     *     pwd: list<string>,
     *     four_ps: list<string>,
     *     solo_parent: list<string>,
     *     indigenous: list<string>
     * }
     */
    public function filters(): array
    {
        return [
            'program' => array_map('intval', $this->validated('program') ?? []),
            'beneficiary_type' => array_values($this->validated('beneficiary_type') ?? []),
            'sex' => array_values($this->validated('sex') ?? []),
            'pwd' => array_values($this->validated('pwd') ?? []),
            'four_ps' => array_values($this->validated('four_ps') ?? []),
            'solo_parent' => array_values($this->validated('solo_parent') ?? []),
            'indigenous' => array_values($this->validated('indigenous') ?? []),
        ];
    }

    protected function userBelongsToDepartment(): bool
    {
        $department = $this->route('department');
        $user = $this->user();

        if (! $department instanceof Department || $user === null) {
            return false;
        }

        return $user->department_id === $department->id;
    }
}
