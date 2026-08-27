<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Listings\RecordListingEvent;
use App\Domain\Listings\ListingAvailability;
use App\Domain\Listings\ListingEventType;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Support\Configurator\ConfiguratorInput;
use App\Support\Configurator\ConfiguratorPageResolver;
use App\Support\Configurator\ListingHighlights;
use App\Support\Story;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

final class ListingController extends ShopController
{
    public function __invoke(Listing $listing, Request $request, RecordListingEvent $recordListingEvent): View
    {
        abort_unless($listing->isOnStorefront(), 404);

        $visitor = $this->visitor();
        $event = $recordListingEvent($listing, $visitor->id, ListingEventType::View, $this->now());

        // A repeat view within the hour writes no row (RecordListingEvent
        // returns null), so the story reads it as a refusal rather than a
        // second `did` for the same visit.
        $story = Story::for(StoryEvent::ListingView);
        $data = ['listing_id' => $listing->id, 'seller_id' => $listing->seller_id];

        $event === null
            ? $story->refused('collapsed a repeat view into the hour already recorded', $data)
            : $story->did('viewed a listing', [...$data, 'status' => $listing->status->value]);

        $hasConfigurator = ConfiguratorPageResolver::hasConfigurator($listing);

        return view('shop.listing', [
            'listing' => $listing->load([
                'seller', 'faqs',
                'descriptionSections' => fn (Relation $query): Relation => $query->orderBy('position'),
                'images' => fn (Relation $query): Relation => $query->orderBy('position'),
            ]),
            'isPurchasable' => ListingAvailability::isPurchasable($listing->status, $listing->quantity),
            'isFavorited' => $visitor->favorites()->where('listing_id', $listing->id)->exists(),
            'hasConfigurator' => $hasConfigurator,
            'configuration' => $hasConfigurator ? ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::fromQuery($request)) : null,
            'highlights' => ListingHighlights::forStorefront($listing),
        ]);
    }
}
