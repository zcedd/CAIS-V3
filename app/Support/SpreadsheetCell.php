<?php

namespace App\Support;

class SpreadsheetCell
{
    public static function sanitize(mixed $value): string
    {
        $string = (string) $value;

        if ($string === '') {
            return '';
        }

        if (preg_match('/^[=+\-@\t\r]/', $string) === 1) {
            return "'".$string;
        }

        return $string;
    }
}
