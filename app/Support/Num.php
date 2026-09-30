<?php

namespace App\Support;

class Num
{
    /**
     * Whole numbers become ints so JSON shows 300 instead of 300.0.
     */
    public static function clean(float|int|string|null $value): float|int
    {
        $value = (float) $value;

        return floor($value) == $value ? (int) $value : $value;
    }

    /**
     * Money as a 2-decimal string, the same format as Laravel's decimal:2 cast.
     */
    public static function money(float|int|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
