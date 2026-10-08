<?php

namespace App\Http\Requests\Executive\Dashboard;

use App\Enums\PermissionName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::ProgramApprove->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $departmentIds = array_values(array_filter(
            array_map('intval', (array) $this->input('department', [])),
        ));

        return [
            'year' => ['nullable', 'array'],
            'year.*' => ['integer', 'min:1900', 'max:'.(now()->year + 1)],
            'quarter' => ['nullable', 'array'],
            'quarter.*' => ['string', Rule::in(['1', '2', '3', '4'])],
            'department' => ['nullable', 'array'],
            'department.*' => [
                'integer',
                Rule::exists('departments', 'id')->whereNull('deleted_at'),
            ],
            'program' => ['nullable', 'array'],
            'program.*' => [
                'integer',
                Rule::exists('programs', 'id')->where(
                    function ($query) use ($departmentIds): void {
                        if ($departmentIds !== []) {
                            $query->whereIn('department_id', $departmentIds);
                        }
                    },
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
     *     year: list<int>,
     *     quarter: list<string>,
     *     department: list<int>,
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
        $year = $this->validated('year') ?? [];

        if ($year === []) {
            $year = [now()->year];
        }

        return [
            'year' => array_map('intval', $year),
            'quarter' => array_values($this->validated('quarter') ?? []),
            'department' => array_map('intval', $this->validated('department') ?? []),
            'program' => array_map('intval', $this->validated('program') ?? []),
            'beneficiary_type' => array_values($this->validated('beneficiary_type') ?? []),
            'sex' => array_values($this->validated('sex') ?? []),
            'pwd' => array_values($this->validated('pwd') ?? []),
            'four_ps' => array_values($this->validated('four_ps') ?? []),
            'solo_parent' => array_values($this->validated('solo_parent') ?? []),
            'indigenous' => array_values($this->validated('indigenous') ?? []),
        ];
    }
}
