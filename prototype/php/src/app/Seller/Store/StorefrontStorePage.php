<?php

declare(strict_types=1);

namespace App\Seller\Store;

use App\Models\Listing;
use App\Models\StoreProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The view data `/s/{slug}` renders from: the profile with everything the
 * shared component reads, the maker's storefront listings in the grid every
 * browse page uses, and the page's own meta.
 */
final readonly class StorefrontStorePage
{
    /**
     * @param  LengthAwarePaginator<int, Listing>  $listings
     */
    public function __construct(
        public StoreProfile $profile,
        public StoreFacts $facts,
        public bool $isOwnStore,
        public LengthAwarePaginator $listings,
        public string $description,
        public ?string $ogImage,
    ) {}
}
