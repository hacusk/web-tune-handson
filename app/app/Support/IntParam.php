<?php

namespace App\Support;

class IntParam
{
    public const MAX_ID = 2147483647;

    public static function inRange(mixed $value, int $min, int $max): ?int
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (! is_string($value) || ! preg_match('/^[0-9]+$/', $value)) {
            return null;
        }
        $n = (int) $value;

        return ($n >= $min && $n <= $max) ? $n : null;
    }
}
