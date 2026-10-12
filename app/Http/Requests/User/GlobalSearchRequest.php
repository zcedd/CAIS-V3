<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GlobalSearchRequest extends FormRequest
{
    /**
     * Department membership is enforced by route middleware.
     * Each result group is authorized separately in the search service.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:100'],
            'scope' => ['nullable', 'string', Rule::in([
                'all',
                'programs',
                'beneficiaries',
                'requests',
                'items',
                'funds',
            ])],
        ];
    }

    public function queryText(): string
    {
        return trim($this->string('q')->toString());
    }

    public function scope(): string
    {
        $scope = $this->string('scope')->toString();

        return $scope === '' ? 'all' : $scope;
    }
}
