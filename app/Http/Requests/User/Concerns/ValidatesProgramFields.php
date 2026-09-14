<?php

namespace App\Http\Requests\User\Concerns;

use App\Enums\ProgramFieldType;
use App\Models\Program;
use App\Models\ProgramField;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesProgramFields
{
    /**
     * @return array<string, mixed>
     */
    protected function programFieldDefinitionRules(): array
    {
        return [
            'fields' => ['nullable', 'array'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::enum(ProgramFieldType::class)],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*' => ['required', 'string', 'max:255'],
            'fields.*.is_required' => ['nullable', 'boolean'],
            'fields.*.show_in_table' => ['nullable', 'boolean'],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function programFieldDefinitionAttributes(): array
    {
        return [
            'fields' => 'custom fields',
            'fields.*.label' => 'field label',
            'fields.*.type' => 'field type',
            'fields.*.options' => 'field options',
            'fields.*.options.*' => 'field option',
            'fields.*.is_required' => 'required field',
            'fields.*.show_in_table' => 'show in table',
            'fields.*.sort_order' => 'field order',
        ];
    }

    protected function afterProgramFieldDefinitions(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $fields = $this->input('fields', []);

            if (! is_array($fields)) {
                return;
            }

            foreach ($fields as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                if (ProgramFieldType::tryFrom((string) ($field['type'] ?? '')) !== ProgramFieldType::Select) {
                    continue;
                }

                $options = $field['options'] ?? null;

                if (! is_array($options) || count($options) < 1) {
                    $validator->errors()->add(
                        "fields.{$index}.options",
                        'Select fields must include at least one option.',
                    );
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function assistanceFieldValueRules(Program $program): array
    {
        $fieldIds = $program->fields()->pluck('id')->all();

        return [
            'field_values' => ['nullable', 'array'],
            'field_values.*.program_field_id' => [
                'required',
                'integer',
                Rule::in($fieldIds),
            ],
            'field_values.*.value' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function assistanceFieldValueAttributes(): array
    {
        return [
            'field_values' => 'custom field values',
            'field_values.*.program_field_id' => 'custom field',
            'field_values.*.value' => 'custom field value',
        ];
    }

    protected function afterAssistanceFieldValues(Validator $validator, Program $program): void
    {
        $validator->after(function (Validator $validator) use ($program): void {
            /** @var Collection<int, ProgramField> $fields */
            $fields = $program->fields()->get()->keyBy('id');
            $submitted = collect($this->input('field_values', []))
                ->filter(fn ($row): bool => is_array($row))
                ->keyBy(fn (array $row): int => (int) ($row['program_field_id'] ?? 0));

            foreach ($fields as $fieldId => $field) {
                $rawValue = $submitted->has($fieldId)
                    ? ($submitted->get($fieldId)['value'] ?? null)
                    : null;

                $isEmpty = $rawValue === null
                    || (is_string($rawValue) && trim($rawValue) === '');

                if ($field->is_required && $isEmpty && $field->type !== ProgramFieldType::Boolean) {
                    $validator->errors()->add(
                        'field_values',
                        "The {$field->label} field is required.",
                    );

                    continue;
                }

                if ($isEmpty) {
                    continue;
                }

                $stringValue = is_bool($rawValue)
                    ? ($rawValue ? '1' : '0')
                    : trim((string) $rawValue);

                match ($field->type) {
                    ProgramFieldType::Number => is_numeric($stringValue)
                        ? null
                        : $validator->errors()->add(
                            'field_values',
                            "The {$field->label} field must be a number.",
                        ),
                    ProgramFieldType::Date => strtotime($stringValue) !== false
                        ? null
                        : $validator->errors()->add(
                            'field_values',
                            "The {$field->label} field must be a valid date.",
                        ),
                    ProgramFieldType::Boolean => in_array(
                        strtolower($stringValue),
                        ['0', '1', 'true', 'false', 'yes', 'no', 'on'],
                        true,
                    )
                        ? null
                        : $validator->errors()->add(
                            'field_values',
                            "The {$field->label} field must be yes or no.",
                        ),
                    ProgramFieldType::Select => in_array($stringValue, $field->options ?? [], true)
                        ? null
                        : $validator->errors()->add(
                            'field_values',
                            "The selected {$field->label} is invalid.",
                        ),
                    default => strlen($stringValue) > 65535
                        ? $validator->errors()->add(
                            'field_values',
                            "The {$field->label} field is too long.",
                        )
                        : null,
                };
            }
        });
    }
}
