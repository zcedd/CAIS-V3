<?php

namespace App\Enums;

enum NameSuffix: string
{
    case Junior = 'Jr.';
    case Senior = 'Sr.';
    case Second = 'II';
    case Third = 'III';
    case Fourth = 'IV';
    case Fifth = 'V';
    case Sixth = 'VI';
    case Seventh = 'VII';
    case Eighth = 'VIII';
    case Ninth = 'IX';
    case Tenth = 'X';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return $this->value;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $suffix): array => [
                'value' => $suffix->value,
                'label' => $suffix->label(),
            ],
            self::cases(),
        );
    }
}
