<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Listings\ListingAvailability;
use App\Models\Customer;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * `/art/{slug}`'s view data — built once so the page's own render and any
 * other response that re-renders the same view (the rate-limited question
 * form, for one) stay in lockstep: the same eager loads, and the same keys
 * the template reads.
 */
final class ListingPagePresenter
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return array<string, mixed>
     */
    public static function forShop(Listing $listing, Customer $visitor, Request $request): array
    {
        $hasConfigurator = ConfiguratorPageResolver::hasConfigurator($listing);
        $focus = $request->query('focus');

        return [
            'listing' => $listing->load([
                'seller', 'faqs',
                'descriptionSections' => fn (Relation $query): Relation => $query->orderBy('position'),
                'images' => fn (Relation $query): Relation => $query->orderBy('position'),
                'listingAttributes.property', 'listingAttributes.propertyValue',
            ]),
            'isPurchasable' => ListingAvailability::isPurchasable($listing->status, $listing->quantity),
            'isFavorited' => $visitor->favorites()->where('listing_id', $listing->id)->exists(),
            'hasConfigurator' => $hasConfigurator,
            'configuration' => $hasConfigurator ? ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::fromQuery($request)) : null,
            'highlights' => ListingHighlights::forStorefront($listing),
            // The control the auto-submit script last changed, so the refreshed
            // page can autofocus it back — round-tripped through the GET query
            // string alongside the axis/unit/modifier selections it caused.
            'focusId' => is_string($focus) ? $focus : null,
        ];
    }
}
