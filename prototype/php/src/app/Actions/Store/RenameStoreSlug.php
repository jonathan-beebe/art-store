<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Models\StoreProfile;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Moves a store to a new address in one transaction: the current
 * `store_slugs` row is stamped retired, the new address becomes a row of its
 * own, and the profile's `slug` column follows. Every address the store has
 * ever answered to survives, so the storefront can redirect from a young
 * retired one.
 *
 * A rename to the address the store already holds writes nothing.
 */
final readonly class RenameStoreSlug
{
    public function __invoke(StoreProfile $profile, string $slug, DateTimeImmutable $retiredAt): StoreProfile
    {
        if ($profile->slug === $slug) {
            return $profile;
        }

        return DB::transaction(function () use ($profile, $slug, $retiredAt): StoreProfile {
            $profile->slugs()->current()->update(['retired_at' => $retiredAt]);

            // An address this store retired earlier comes back as the
            // current one; the row it already has is the one updated.
            $profile->slugs()->updateOrCreate(['slug' => $slug], ['retired_at' => null]);

            $profile->update(['slug' => $slug]);

            return $profile;
        });
    }
}
