<?php

namespace App\Http\Requests\User\UnspscCode;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', [Item::class, $this->route('department')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'curated' => ['nullable', 'boolean'],
        ];
    }

    public function search(): string
    {
        return trim((string) $this->validated('q', ''));
    }

    public function curatedOnly(): bool
    {
        if (! $this->exists('curated')) {
            return true;
        }

        return $this->boolean('curated');
    }
}
