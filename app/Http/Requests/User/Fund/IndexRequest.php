<?php

namespace App\Http\Requests\User\Fund;

use App\Models\Fund;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('viewAny', [Fund::class, $this->route('department')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 20, 25, 30, 40, 50])],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', 'string', Rule::in(['name', 'amount', 'year', 'is_active'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    public function search(): string
    {
        return trim($this->validated('search') ?? '');
    }

    /**
     * @return list<string>
     */
    public function statuses(): array
    {
        return array_values($this->validated('status') ?? []);
    }

    public function sort(): string
    {
        return $this->validated('sort') ?? 'name';
    }

    public function direction(): string
    {
        return $this->validated('direction') ?? 'asc';
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 15);
    }
}
