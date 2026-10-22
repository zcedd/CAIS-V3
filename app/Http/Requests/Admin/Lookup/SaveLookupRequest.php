<?php

namespace App\Http\Requests\Admin\Lookup;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

abstract class SaveLookupRequest extends FormRequest
{
    abstract protected function lookupTable(): string;

    protected function softDeletes(): bool
    {
        return false;
    }

    protected function ignoredId(): ?int
    {
        return null;
    }

    protected function cityColumn(): ?string
    {
        return null;
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueNameRule()],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('name') && is_string($this->input('name'))) {
            $this->merge([
                'name' => trim($this->string('name')->toString()),
            ]);
        }
    }

    /**
     * @return array{name: string, is_active: bool}
     */
    public function lookupAttributes(): array
    {
        /** @var array{name: string, is_active: mixed} $validated */
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'is_active' => $this->boolean('is_active'),
        ];
    }

    private function uniqueNameRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $cityColumn = $this->cityColumn();

            $exists = DB::table($this->lookupTable())
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])
                ->when($this->softDeletes(), fn ($query) => $query->whereNull('deleted_at'))
                ->when($this->ignoredId() !== null, fn ($query) => $query->where('id', '!=', $this->ignoredId()))
                ->when($cityColumn !== null, fn ($query) => $query->where($cityColumn, $this->integer($cityColumn)))
                ->exists();

            if ($exists) {
                $fail('The name has already been taken.');
            }
        };
    }
}
