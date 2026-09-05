<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Models\Listing;
use App\Models\ListingImage;

/**
 * The view data every Images screen render needs, from the plain index page
 * to the re-render a rate-limited save falls back to — one place so every
 * route that lands on this screen builds the same shape.
 */
final class ListingImageIndexPageData
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return array<string, mixed>
     */
    public static function build(Listing $listing): array
    {
        return [
            'listing' => $listing,
            'images' => $listing->images()->orderBy('position')->get(),
            'maxImages' => ListingImage::MAX_PER_LISTING,
        ];
    }
}
