<?php

namespace App\Support;

final class BookFormat
{
    public static function options(): array
    {
        return ['hardcover' => 'Hardcover / hardback', 'paperback' => 'Paperback / softcover',
            'board_book' => 'Board book', 'spiral_bound' => 'Spiral bound', 'ring_bound' => 'Ring bound',
            'loose_leaf' => 'Loose leaf', 'boxed_set' => 'Boxed set', 'ebook' => 'E-book',
            'audiobook' => 'Audiobook', 'other' => 'Other'];
    }

    public static function normalise(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = preg_replace('/[\s_-]+/', ' ', mb_strtolower(trim($value)));
        foreach (self::options() as $code => $label) {
            if (str_replace('_', ' ', $code) === $value || mb_strtolower($label) === $value) {
                return $code;
            }
        }

        return match ($value) {
            'hardback', 'hard back', 'hard cover' => 'hardcover',
            'softcover', 'soft cover', 'softback', 'soft back', 'trade paperback', 'mass market paperback' => 'paperback',
            'boardbook' => 'board_book', 'spiralbound', 'spiral binding' => 'spiral_bound',
            'ringbound', 'ring binding' => 'ring_bound', 'looseleaf' => 'loose_leaf',
            'box set', 'boxset' => 'boxed_set', 'e book', 'digital', 'electronic book' => 'ebook',
            'audio book' => 'audiobook',
            default => null,
        };
    }
}
