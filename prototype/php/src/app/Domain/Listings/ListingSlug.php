<?php

declare(strict_types=1);

namespace App\Domain\Listings;

final class ListingSlug
{
    private const FALLBACK = 'listing';

    /**
     * The slug a title asks for, before any collision with another listing.
     */
    private function __construct() {} // @codeCoverageIgnore

    public static function base(string $title): string
    {
        return self::transliterate($title) ?: self::FALLBACK;
    }

    private static function transliterate(string $title): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: '';
        $lower = mb_strtolower($ascii);
        $hyphenated = preg_replace('/[^a-z0-9]+/', '-', $lower) ?? '';

        return trim($hyphenated, '-');
    }

    /**
     * @param  list<string>  $taken
     */
    public static function firstFree(string $title, array $taken): string
    {
        $base = self::base($title);

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        $suffix = 2;
        while (in_array("{$base}-{$suffix}", $taken, true)) {
            $suffix++;
        }

        return "{$base}-{$suffix}";
    }
}
