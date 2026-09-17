<?php

namespace App\Services\Workflow;

use App\Enums\AssistanceItemOrigin;
use App\Enums\ItemKind;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use InvalidArgumentException;

class WorkflowConditionEvaluator
{
    /**
     * @var list<string>
     */
    private const OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'in'];

    /**
     * @var list<string>
     */
    private const FIELDS = ['amount', 'item_kind', 'current_status', 'current_sub_status'];

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $conditions
     */
    public function matches(Assistance $assistance, mixed $conditions): bool
    {
        if ($conditions === null || $conditions === [] || $conditions === '') {
            return true;
        }

        if (! is_array($conditions)) {
            return false;
        }

        if ($this->isGroup($conditions)) {
            $operator = strtoupper((string) ($conditions['logic'] ?? $conditions['operator'] ?? 'AND'));
            $rules = $conditions['conditions'] ?? $conditions['rules'] ?? [];

            if (! is_array($rules) || $rules === []) {
                return true;
            }

            $results = array_map(
                fn (mixed $rule): bool => is_array($rule) && $this->matches($assistance, $rule),
                $rules,
            );

            return $operator === 'OR'
                ? in_array(true, $results, true)
                : ! in_array(false, $results, true);
        }

        return $this->matchesRule($assistance, $conditions);
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    private function isGroup(array $conditions): bool
    {
        return isset($conditions['conditions']) || isset($conditions['rules']) || isset($conditions['logic']);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function matchesRule(Assistance $assistance, array $rule): bool
    {
        $field = (string) ($rule['field'] ?? '');
        $operator = (string) ($rule['operator'] ?? '=');
        $expected = $rule['value'] ?? null;

        if (! in_array($field, self::FIELDS, true)) {
            throw new InvalidArgumentException("Unsupported workflow condition field [{$field}].");
        }

        if (! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Unsupported workflow condition operator [{$operator}].");
        }

        $actual = $this->resolve($assistance, $field);

        return $this->compare($actual, $operator, $expected);
    }

    private function resolve(Assistance $assistance, string $field): mixed
    {
        $assistance->loadMissing([
            'currentRequestSubStatus.requestStatus',
            'assistanceItem.item',
        ]);

        return match ($field) {
            'amount' => $this->cashAmount($assistance),
            'item_kind' => $this->primaryItemKind($assistance),
            'current_status' => $assistance->currentRequestSubStatus?->requestStatus?->code?->value
                ?? $assistance->currentRequestSubStatus?->requestStatus?->name,
            'current_sub_status' => $assistance->currentRequestSubStatus?->code?->value
                ?? $assistance->currentRequestSubStatus?->name,
            default => null,
        };
    }

    private function cashAmount(Assistance $assistance): int
    {
        return (int) $assistance->assistanceItem
            ->filter(static function (AssistanceItem $line): bool {
                $origin = $line->origin;

                return $origin === AssistanceItemOrigin::Requested
                    && $line->item?->kind === ItemKind::Cash;
            })
            ->sum(static fn (AssistanceItem $line): int => (int) ($line->requested_quantity ?? $line->quantity));
    }

    private function primaryItemKind(Assistance $assistance): ?string
    {
        $kind = $assistance->assistanceItem
            ->first(static fn (AssistanceItem $line): bool => $line->origin === AssistanceItemOrigin::Requested)
            ?->item
            ?->kind;

        return $kind instanceof ItemKind ? $kind->value : null;
    }

    private function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        if ($operator === 'in') {
            $haystack = is_array($expected) ? $expected : explode(',', (string) $expected);

            return in_array($actual, array_map(static fn (mixed $value): mixed => $this->normalize($value), $haystack), true)
                || in_array((string) $actual, array_map(static fn (mixed $value): string => (string) $value, $haystack), true);
        }

        $left = $this->normalize($actual);
        $right = $this->normalize($expected);

        return match ($operator) {
            '=' => $left == $right,
            '!=' => $left != $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            default => false,
        };
    }

    private function normalize(mixed $value): mixed
    {
        if (is_numeric($value) && ! is_bool($value)) {
            return $value + 0;
        }

        if (is_object($value) && property_exists($value, 'value')) {
            return $value->value;
        }

        return $value;
    }
}
