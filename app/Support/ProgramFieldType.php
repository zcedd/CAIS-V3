<?php

namespace App\Support;

class ProgramFieldType
{
    public const Text = 'text';

    public const Textarea = 'textarea';

    public const Number = 'number';

    public const Date = 'date';

    public const Boolean = 'boolean';

    public const Select = 'select';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::Text,
            self::Textarea,
            self::Number,
            self::Date,
            self::Boolean,
            self::Select,
        ];
    }
}
