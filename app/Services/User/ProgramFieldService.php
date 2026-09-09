<?php

namespace App\Services\User;

use App\Enums\ProgramFieldType;
use App\Models\Assistance;
use App\Models\AssistanceFieldValue;
use App\Models\Program;
use App\Models\ProgramField;
use App\Support\EmptyCell;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProgramFieldService
{
    /**
     * @param  list<array{
     *     id?: int|null,
     *     label: string,
     *     type: string,
     *     options?: list<string>|null,
     *     is_required?: bool,
     *     show_in_table?: bool,
     *     sort_order?: int
     * }>  $fields
     */
    public function syncForProgram(Program $program, array $fields): void
    {
        $existingFields = $program->fields()->get()->keyBy('id');
        $retainedIds = [];

        foreach (array_values($fields) as $index => $fieldData) {
            $fieldId = isset($fieldData['id']) ? (int) $fieldData['id'] : null;
            $payload = [
                'label' => $fieldData['label'],
                'type' => $fieldData['type'],
                'options' => ProgramFieldType::tryFrom((string) $fieldData['type']) === ProgramFieldType::Select
                    ? array_values($fieldData['options'] ?? [])
                    : null,
                'is_required' => (bool) ($fieldData['is_required'] ?? false),
                'show_in_table' => (bool) ($fieldData['show_in_table'] ?? false),
                'sort_order' => (int) ($fieldData['sort_order'] ?? $index),
            ];

            if ($fieldId !== null && $existingFields->has($fieldId)) {
                /** @var ProgramField $field */
                $field = $existingFields->get($fieldId);
                $field->update($payload);
                $retainedIds[] = $fieldId;

                continue;
            }

            $program->fields()->create([
                ...$payload,
                'key' => $this->uniqueKeyForProgram($program, $fieldData['label']),
            ]);
        }

        $idsToDelete = $existingFields->keys()
            ->reject(fn (int|string $id): bool => in_array((int) $id, $retainedIds, true))
            ->all();

        if ($idsToDelete !== []) {
            ProgramField::query()
                ->where('program_id', $program->id)
                ->whereIn('id', $idsToDelete)
                ->delete();
        }
    }

    /**
     * @param  list<array{program_field_id: int, value?: string|null}>  $fieldValues
     */
    public function syncValuesForAssistance(Assistance $assistance, array $fieldValues): void
    {
        $programFields = ProgramField::query()
            ->where('program_id', $assistance->program_id)
            ->get()
            ->keyBy('id');

        $submitted = collect($fieldValues)
            ->keyBy(fn (array $row): int => (int) $row['program_field_id']);

        foreach ($programFields as $fieldId => $field) {
            $rawValue = $submitted->has($fieldId)
                ? ($submitted->get($fieldId)['value'] ?? null)
                : null;

            $normalized = $this->normalizeValue($field, $rawValue);

            if ($normalized === null || $normalized === '') {
                AssistanceFieldValue::query()
                    ->where('assistance_id', $assistance->id)
                    ->where('program_field_id', $fieldId)
                    ->delete();

                continue;
            }

            AssistanceFieldValue::query()->updateOrCreate(
                [
                    'assistance_id' => $assistance->id,
                    'program_field_id' => $fieldId,
                ],
                ['value' => $normalized],
            );
        }
    }

    public function copyToProgram(Program $source, Program $target): void
    {
        $source->loadMissing('fields');

        foreach ($source->fields as $field) {
            $target->fields()->create([
                'label' => $field->label,
                'key' => $this->uniqueKeyForProgram($target, $field->label),
                'type' => $field->type,
                'options' => $field->options,
                'is_required' => $field->is_required,
                'show_in_table' => $field->show_in_table,
                'sort_order' => $field->sort_order,
            ]);
        }
    }

    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     key: string,
     *     type: string,
     *     options: list<string>|null,
     *     is_required: bool,
     *     show_in_table: bool,
     *     sort_order: int
     * }>
     */
    public function fieldsPayload(Program $program): array
    {
        return $program->fields()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(static fn (ProgramField $field): array => [
                'id' => $field->id,
                'label' => $field->label,
                'key' => $field->key,
                'type' => $field->type,
                'options' => $field->options,
                'is_required' => $field->is_required,
                'show_in_table' => $field->show_in_table,
                'sort_order' => $field->sort_order,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AssistanceFieldValue>  $values
     * @return array<string, string|null>
     */
    public function valuesKeyedByFieldKey(Collection $values): array
    {
        $result = [];

        foreach ($values as $value) {
            $key = $value->programField?->key;

            if ($key === null) {
                continue;
            }

            $result[$key] = $value->value;
        }

        return $result;
    }

    public function formatDisplayValue(ProgramField $field, ?string $value): string
    {
        if ($value === null || $value === '') {
            return EmptyCell::VALUE;
        }

        return match ($field->type) {
            ProgramFieldType::Boolean => in_array($value, ['1', 'true', 'yes'], true) ? 'Yes' : 'No',
            default => $value,
        };
    }

    private function uniqueKeyForProgram(Program $program, string $label): string
    {
        $base = Str::slug($label, '_');

        if ($base === '') {
            $base = 'field';
        }

        $key = $base;
        $suffix = 2;

        while (
            ProgramField::withTrashed()
                ->where('program_id', $program->id)
                ->where('key', $key)
                ->exists()
        ) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }

    private function normalizeValue(ProgramField $field, mixed $rawValue): ?string
    {
        if ($rawValue === null) {
            return null;
        }

        if (is_bool($rawValue)) {
            return $rawValue ? '1' : '0';
        }

        $value = is_string($rawValue) ? trim($rawValue) : (string) $rawValue;

        if ($value === '') {
            return null;
        }

        return match ($field->type) {
            ProgramFieldType::Boolean => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true)
                ? '1'
                : '0',
            ProgramFieldType::Number => is_numeric($value) ? (string) $value : throw ValidationException::withMessages([
                'field_values' => ["The {$field->label} field must be a number."],
            ]),
            default => $value,
        };
    }
}
