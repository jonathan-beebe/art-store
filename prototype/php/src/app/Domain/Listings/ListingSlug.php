<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use Illuminate\Support\Str;

final class ListingSlug
{
    private const FALLBACK = 'listing';

    /**
     * The slug a title asks for, before any collision with another listing.
     */
    public static function base(string $title): string
    {
        return Str::slug($title) ?: self::FALLBACK;
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
