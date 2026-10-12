<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Http\Requests\User\Beneficiary\Concerns\AuthorizesDepartmentBeneficiary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    use AuthorizesDepartmentBeneficiary;

    public function authorize(): bool
    {
        return $this->canViewAnyBeneficiaries();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 25, 50, 100])],
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'array'],
            'type.*' => ['string', Rule::in(['individual', 'organization'])],
            'city' => ['nullable', 'integer', Rule::exists('address_cities', 'id')],
            'sector' => ['nullable', 'string', Rule::in([
                'pwd',
                'solo_parent',
                'four_ps',
                'indigenous',
                'senior',
            ])],
            'sort' => ['nullable', 'string', Rule::in(['name', 'newest', 'most_assisted'])],
        ];
    }

    public function search(): string
    {
        return trim($this->validated('search') ?? '');
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 25);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_values($this->validated('type') ?? []);
    }

    public function cityId(): ?int
    {
        $city = $this->validated('city');

        return $city === null ? null : (int) $city;
    }

    public function sector(): ?string
    {
        $sector = $this->validated('sector');

        return is_string($sector) && $sector !== '' ? $sector : null;
    }

    public function sort(): string
    {
        return $this->validated('sort') ?? 'name';
    }
}
