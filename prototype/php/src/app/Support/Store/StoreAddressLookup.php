<?php

declare(strict_types=1);

namespace App\Support\Store;

use App\Domain\Store\RetiredSlugWindow;
use App\Models\StoreProfile;
use App\Models\StoreSlug;
use DateTimeImmutable;

/**
 * What `/s/{slug}` is looking at: the store that answers to the address
 * today, or the address one that recently moved now answers to.
 *
 * The profile carries the current slug for the unique index and the fast
 * lookup, so a hit needs one query; only a miss goes on to read the
 * history.
 */
final readonly class StoreAddressLookup
{
    public function current(string $slug): ?StoreProfile
    {
        return StoreProfile::query()->where('slug', $slug)->first();
    }

    /**
     * The address a store moved to, for an address it left behind inside
     * {@see RetiredSlugWindow}. Null when nothing has answered to the
     * address, or when it was retired too long ago to forward.
     */
    public function movedTo(string $slug, DateTimeImmutable $now): ?string
    {
        $retired = StoreSlug::query()->retired()->where('slug', $slug)->first();

        if (! $retired instanceof StoreSlug) {
            return null;
        }

        $retiredAt = $retired->retired_at?->toDateTimeImmutable();

        if ($retiredAt === null || ! RetiredSlugWindow::stillForwards($retiredAt, $now)) {
            return null;
        }

        return $retired->storeProfile?->slug;
    }
}
