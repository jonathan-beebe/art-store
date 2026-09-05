<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Logging\Story;
use App\Logging\StoryEvent;
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
 * A rename to the address the store already holds writes nothing, and tells
 * no story: `SaveStore` calls this on every save, and a save that leaves the
 * address untouched is not a rename.
 */
final readonly class RenameStoreSlug
{
    public function __invoke(StoreProfile $profile, string $slug, DateTimeImmutable $retiredAt): StoreProfile
    {
        if ($profile->slug === $slug) {
            return $profile;
        }

        $from = $profile->slug;

        return Story::for(StoryEvent::StoreSlugRename)->tell('renaming a store slug', [
            'seller_id' => $profile->seller_id,
            'store_profile_id' => $profile->id,
            'slug_from' => $from,
            'slug_to' => $slug,
        ], function (Story $story) use ($profile, $slug, $retiredAt, $from): StoreProfile {
            $renamed = DB::transaction(function () use ($profile, $slug, $retiredAt): StoreProfile {
                $profile->slugs()->current()->update(['retired_at' => $retiredAt]);

                // An address this store retired earlier comes back as the
                // current one; the row it already has is the one updated.
                $profile->slugs()->updateOrCreate(['slug' => $slug], ['retired_at' => null]);

                $profile->update(['slug' => $slug]);

                return $profile;
            });

            $story->did('renamed the store slug', [
                'seller_id' => $renamed->seller_id,
                'store_profile_id' => $renamed->id,
                'slug_from' => $from,
                'slug_to' => $renamed->slug,
            ]);

            return $renamed;
        });
    }
}
