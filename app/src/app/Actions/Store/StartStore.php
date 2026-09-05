<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Domain\Store\StoreSlug as StoreSlugRule;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\Seller;
use App\Models\StoreProfile;
use App\Models\StoreSlug;
use Illuminate\Support\Facades\DB;

/**
 * The store a seller already has, or a hidden one named after their shop.
 * The Store screen needs a row to hang sections and pictures on, so the
 * first visit is what mints it — the same shape
 * {@see \App\Models\Customer::cart()} gives a storefront visitor.
 */
final readonly class StartStore
{
    public function __invoke(Seller $seller): StoreProfile
    {
        $existing = $seller->storeProfile()->first();

        if ($existing instanceof StoreProfile) {
            return $existing;
        }

        return Story::for(StoryEvent::StoreStart)->tell('minting a store for a seller', [
            'seller_id' => $seller->id,
        ], function (Story $story) use ($seller): StoreProfile {
            $profile = DB::transaction(function () use ($seller): StoreProfile {
                $name = $seller->displayName();

                $profile = StoreProfile::create([
                    'seller_id' => $seller->id,
                    'slug' => StoreSlugRule::firstFree($name, $this->slugsTaken()),
                    'name' => $name,
                    'published_at' => null,
                ]);

                StoreSlug::create(['store_profile_id' => $profile->id, 'slug' => $profile->slug]);

                return $profile;
            });

            $story->did('minted a store for the seller', [
                'seller_id' => $seller->id,
                'store_profile_id' => $profile->id,
                'slug' => $profile->slug,
            ]);

            return $profile;
        });
    }

    /**
     * Every address any store has ever answered to — the current ones and
     * the retired ones alike, since `store_slugs.slug` is unique across the
     * table.
     *
     * @return list<string>
     */
    private function slugsTaken(): array
    {
        /** @var list<string> $slugs */
        $slugs = array_values(StoreSlug::query()->pluck('slug')->all());

        return $slugs;
    }
}
