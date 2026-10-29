<?php

namespace App\Support;

use Symfony\Component\Intl\Languages;

final class BookLanguage
{
    public static function options(): array
    {
        static $options;
        if ($options === null) {
            $options = Languages::getNames('en');
            $options['mul'] = 'Multiple languages';
            $options['und'] = 'Undetermined';
            $options['zxx'] = 'No linguistic content';
            asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $options;
    }

    public static function normalise(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $value = mb_strtolower(trim($value));
        if (str_starts_with($value, '/languages/')) {
            $value = basename($value);
        }
        // ISO 639-2 bibliographic spellings used by library catalogues.
        $value = ['alb' => 'sqi', 'arm' => 'hye', 'baq' => 'eus', 'bur' => 'mya',
            'chi' => 'zho', 'cze' => 'ces', 'dut' => 'nld', 'fre' => 'fra', 'geo' => 'kat',
            'ger' => 'deu', 'gre' => 'ell', 'ice' => 'isl', 'mac' => 'mkd', 'mao' => 'mri',
            'may' => 'msa', 'per' => 'fas', 'rum' => 'ron', 'slo' => 'slk', 'tib' => 'bod', 'wel' => 'cym'][$value] ?? $value;
        // Regional/script variants share the base language filter.
        if (preg_match('/^([a-z]{2,3})(?:[-_][a-z0-9]{2,8})+$/', $value, $matches)) {
            $value = $matches[1];
        }
        if (isset(self::options()[$value])) {
            return $value;
        }
        if (strlen($value) === 3 && Languages::alpha3CodeExists($value)) {
            $value = Languages::getAlpha2Code($value) ?? $value;
        }
        if (isset(self::options()[$value])) {
            return $value;
        }
        foreach (self::options() as $code => $label) {
            if (mb_strtolower($label) === $value) {
                return $code;
            }
        }

        return null;
    }
}
