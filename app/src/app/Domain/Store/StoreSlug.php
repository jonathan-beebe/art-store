<?php

declare(strict_types=1);

namespace App\Domain\Store;

/**
 * The address half of `/s/{slug}`: lowercase letters, digits, and single
 * hyphens between them, 3 to 60 characters. The store's current slug lives
 * on the profile row for the unique index and the fast lookup; every slug
 * the store has ever answered to is a row of its own.
 */
final class StoreSlug
{
    public const int MIN_LENGTH = 3;

    public const int MAX_LENGTH = 60;

    /**
     * The address a store falls back to when its name transliterates to
     * nothing a slug can hold.
     */
    private const string FALLBACK = 'store';

    private const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private function __construct() {} // @codeCoverageIgnore

    public static function isValid(string $slug): bool
    {
        $length = strlen($slug);

        return $length >= self::MIN_LENGTH
            && $length <= self::MAX_LENGTH
            && preg_match(self::PATTERN, $slug) === 1;
    }

    /**
     * The address a store name asks for, before any collision with another
     * store. Always a slug {@see isValid()} accepts: a name that
     * transliterates to nothing becomes the fallback, and one that
     * transliterates too short is suffixed with it.
     */
    public static function fromName(string $name): string
    {
        $base = self::truncate(self::transliterate($name));

        if ($base === '') {
            return self::FALLBACK;
        }

        return strlen($base) < self::MIN_LENGTH
            ? self::truncate($base.'-'.self::FALLBACK)
            : $base;
    }

    /**
     * The first address free of `$taken`, counting up from the one the name
     * asks for. The base is trimmed so the numeric suffix fits inside
     * {@see MAX_LENGTH}.
     *
     * @param  list<string>  $taken
     */
    public static function firstFree(string $name, array $taken): string
    {
        $base = self::fromName($name);

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        $suffix = 2;

        while (in_array($candidate = self::withSuffix($base, $suffix), $taken, true)) {
            $suffix++;
        }

        return $candidate;
    }

    private static function withSuffix(string $base, int $suffix): string
    {
        $tail = '-'.$suffix;

        return self::truncate($base, self::MAX_LENGTH - strlen($tail)).$tail;
    }

    private static function transliterate(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: '';
        $lower = mb_strtolower($ascii);
        $hyphenated = preg_replace('/[^a-z0-9]+/', '-', $lower) ?? '';

        return trim($hyphenated, '-');
    }

    private static function truncate(string $slug, int $length = self::MAX_LENGTH): string
    {
        return trim(substr($slug, 0, $length), '-');
    }
}
