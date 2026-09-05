<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * The avatar reduction every seller screen reads a name by: the first
 * letter of each of the first two words.
 */
final class Initials
{
    private function __construct() {} // @codeCoverageIgnore

    public static function of(string $name): string
    {
        $words = array_filter(preg_split('/\s+/', trim($name)) ?: []);

        $letters = array_map(
            fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)),
            array_slice($words, 0, 2),
        );

        return implode('', $letters);
    }
}
