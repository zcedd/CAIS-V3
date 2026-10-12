<?php

namespace App\Support;

final class DecimalAmount
{
    public static function of(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        if (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }

        if (! is_numeric((string) $value)) {
            return '0.00';
        }

        return bcadd((string) $value, '0', 2);
    }

    public static function add(int|float|string|null $left, int|float|string|null $right): string
    {
        return bcadd(self::of($left), self::of($right), 2);
    }

    public static function sub(int|float|string|null $left, int|float|string|null $right): string
    {
        return bcsub(self::of($left), self::of($right), 2);
    }

    public static function comp(int|float|string|null $left, int|float|string|null $right): int
    {
        return bccomp(self::of($left), self::of($right), 2);
    }

    public static function gt(int|float|string|null $left, int|float|string|null $right): bool
    {
        return self::comp($left, $right) === 1;
    }

    public static function isZero(int|float|string|null $value): bool
    {
        return self::comp($value, '0') === 0;
    }

    public static function isPositive(int|float|string|null $value): bool
    {
        return self::comp($value, '0') === 1;
    }

    public static function isWhole(int|float|string|null $value): bool
    {
        return str_ends_with(self::of($value), '.00');
    }

    public static function toInt(int|float|string|null $value): int
    {
        return (int) self::of($value);
    }
}
