<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Admin\EntityActivity;
use App\Analytics\Admin\Funnel;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsEntityQueryRequest;
use App\Models\Listing;
use Illuminate\View\View;

/**
 * `/admin/analytics/listings/{listing}`, the drill-in's one-listing page:
 * identity, range tiles, a daily strip, and the event feed.
 * `App\Analytics\Admin\EntityActivity` does the read; this assembles it
 * with the page's own segmented controls and action links.
 */
final class ListingController extends Controller
{
    private const array RANGE_LABELS = [7 => '7d', 30 => '30d', 90 => '90d'];

    /** The entry and event pages' own copy for each event name — see
     * {@see \App\Analytics\Admin\EventTotals::EVENT_LABELS}, duplicated here
     * the same way every page that shows this vocabulary keeps its own
     * copy of it. */
    private const array EVENT_LABELS = [
        'listing.view' => 'Listing views',
        'listing.favorite' => 'Favorites',
        'listing.unfavorite' => 'Unfavorites',
        'listing.cart_add' => 'Cart adds',
        'checkout.open' => 'Checkouts opened',
        'order.place' => 'Orders placed',
        'order.pay' => 'Orders paid',
        'order.cancel' => 'Orders cancelled',
    ];

    public function show(Listing $listing, AnalyticsEntityQueryRequest $request): View
    {
        $roundTripped = $request->roundTripped();
        $rangeDays = $request->rangeDays();
        $filter = $request->eventFilter();

        $range = AnalyticsRange::of($rangeDays, $this->now());
        $activity = EntityActivity::forListing($listing, $range, $filter);

        return view('admin.analytics.entities.show', [
            'activity' => $activity,
            'funnel' => Funnel::forListing($listing->id, $range),
            'now' => $this->now(),
            'rangeCaption' => $range->caption(),
            'rangeLinks' => $this->entityRangeLinks($listing->id, $roundTripped, $rangeDays),
            'eventLinks' => $this->entityEventLinks($listing->id, $roundTripped, $filter),
            'backHref' => route('admin.analytics.index', array_intersect_key($roundTripped, ['range' => true])),
            'backLabel' => 'Analytics',
            'actions' => $this->actions($listing),
        ]);
    }

    /**
     * The listing page's range segmented control: `event` carried through
     * unchanged.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function entityRangeLinks(string $listingId, array $roundTripped, int $current): array
    {
        $without = collect($roundTripped)->except('range')->all();

        return array_map(
            fn (int $days): array => [
                'label' => self::RANGE_LABELS[$days],
                'href' => route('admin.analytics.listings.show', ['listing' => $listingId, ...$without, 'range' => $days]),
                'active' => $days === $current,
            ],
            AnalyticsRange::SIZES,
        );
    }

    /**
     * The listing page's event-name segmented control: "All" plus one link
     * per {@see AnalyticsEventName} case, `range` carried through unchanged.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function entityEventLinks(string $listingId, array $roundTripped, ?AnalyticsEventName $current): array
    {
        $without = collect($roundTripped)->except('event')->all();

        $links = [[
            'label' => 'All',
            'href' => route('admin.analytics.listings.show', ['listing' => $listingId, ...$without]),
            'active' => $current === null,
        ]];

        foreach (AnalyticsEventName::cases() as $name) {
            $links[] = [
                'label' => self::EVENT_LABELS[$name->value],
                'href' => route('admin.analytics.listings.show', ['listing' => $listingId, ...$without, 'event' => $name->value]),
                'active' => $current === $name,
            ];
        }

        return $links;
    }

    /**
     * The identity card's action column: open the listing. The log viewer
     * filters by actor only ({@see \App\Http\Requests\Admin\LogsQueryRequest}),
     * so a listing page has no "Open in logs" link to offer.
     *
     * @return list<array{label: string, href: string, variant: string}>
     */
    private function actions(Listing $listing): array
    {
        return [
            ['label' => 'Open listing', 'href' => route('admin.listings.show', $listing), 'variant' => 'primary'],
        ];
    }
}
