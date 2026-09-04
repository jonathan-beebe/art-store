<?php

declare(strict_types=1);

namespace App\Support\Store;

use App\Models\Listing;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * The view data `/s/{slug}` renders from: the profile with everything the
 * shared component reads, the maker's storefront listings in the grid every
 * browse page uses, and the page's own meta.
 */
final class StorefrontStorePageData
{
    /** Listings per page, the storefront grid's own page size. */
    private const int PER_PAGE = 12;

    /** The longest description a search result or a link preview shows. */
    private const int DESCRIPTION_LENGTH = 160;

    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return array<string, mixed>
     */
    public static function build(StoreProfile $profile, bool $isOwnStore): array
    {
        $profile->load([
            'portraitImage',
            'coverImage',
            'links',
            'sections.sectionImages.storeImage',
        ]);

        return [
            'profile' => $profile,
            'facts' => StoreFacts::of($profile),
            'isOwnStore' => $isOwnStore,
            'listings' => Listing::query()
                ->where('seller_id', $profile->seller_id)
                ->onStorefront()
                ->with(['seller.storeProfile', 'images' => fn (Relation $images): Relation => $images->orderBy('position')])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
            'description' => self::description($profile),
            'ogImage' => $profile->coverImage?->url() ?? $profile->portraitImage?->url(),
        ];
    }

    /**
     * The tagline, or the opening of the first story the page carries, or
     * the store's name — whichever the seller has written.
     */
    private static function description(StoreProfile $profile): string
    {
        $tagline = $profile->tagline;

        if ($tagline !== null) {
            return $tagline;
        }

        $story = $profile->sections
            ->first(fn (StoreSection $section): bool => $section->body !== null)?->body;

        return $story === null
            ? $profile->name
            : Str::limit($story, self::DESCRIPTION_LENGTH);
    }
}
