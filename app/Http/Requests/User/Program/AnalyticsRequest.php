<?php

namespace App\Http\Requests\User\Program;

use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $program = $this->route('program');

        return $program instanceof Program && Gate::allows('view', $program);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
            'sex' => ['nullable', 'string', Rule::in(['Female', 'Male'])],
            'classification' => ['nullable', 'string', Rule::in([
                'pwd',
                'solo_parent',
                'four_ps',
                'indigenous',
                'senior',
            ])],
        ];
    }

    public function search(): string
    {
        return trim($this->validated('search') ?? '');
    }

    public function sex(): ?string
    {
        $sex = $this->validated('sex');

        return is_string($sex) && $sex !== '' ? $sex : null;
    }

    public function classification(): ?string
    {
        $classification = $this->validated('classification');

        return is_string($classification) && $classification !== '' ? $classification : null;
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }
}
