<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class Isbn
{
    public static function canonical(string $input): string
    {
        $value = strtoupper(preg_replace('/[\s-]/', '', $input));
        if (preg_match('/^\d{9}[\dX]$/', $value)) {
            $sum = 0;
            foreach (str_split($value) as $i => $digit) {
                $sum += ($digit === 'X' ? 10 : (int) $digit) * (10 - $i);
            }
            if ($sum % 11 === 0) {
                $base = '978'.substr($value, 0, 9);

                return $base.self::checkDigit($base);
            }
        }
        if (preg_match('/^97[89]\d{10}$/', $value) && self::checkDigit(substr($value, 0, 12)) === (int) $value[12]) {
            return $value;
        }
        throw ValidationException::withMessages(['isbn' => 'Enter a valid book ISBN-10 or ISBN-13.']);
    }

    private static function checkDigit(string $base): int
    {
        $sum = 0;
        foreach (str_split($base) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10;
    }
}
