<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Admin\EntityActivity;
use App\Analytics\Admin\EntityPageLinks;
use App\Analytics\Admin\Funnel;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\FunnelDefinition;
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
    private const string ROUTE_NAME = 'admin.analytics.listings.show';

    private const string PARAM_KEY = 'listing';

    public function show(Listing $listing, AnalyticsEntityQueryRequest $request): View
    {
        $roundTripped = $request->roundTripped();
        $rangeDays = $request->rangeDays();
        $filter = $request->eventFilter();

        $range = AnalyticsRange::of($rangeDays, $this->now());
        $activity = EntityActivity::forListing($listing, $range, $filter);

        return view('admin.analytics.entities.show', [
            'activity' => $activity,
            'funnel' => Funnel::forListing(FunnelDefinition::storefront(), $listing->id, $range),
            'now' => $this->now(),
            'rangeCaption' => $range->caption(),
            'rangeLinks' => EntityPageLinks::range(self::ROUTE_NAME, self::PARAM_KEY, $listing->id, $roundTripped, $rangeDays),
            'eventLinks' => EntityPageLinks::event(self::ROUTE_NAME, self::PARAM_KEY, $listing->id, $roundTripped, $filter, AnalyticsEventName::forSubject('listing')),
            'backHref' => route('admin.analytics.index', array_intersect_key($roundTripped, ['range' => true])),
            'backLabel' => 'Analytics',
            'actions' => $this->actions($listing),
        ]);
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
