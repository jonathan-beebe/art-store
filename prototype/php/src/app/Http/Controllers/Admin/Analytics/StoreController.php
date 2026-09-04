<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Admin\EntityActivity;
use App\Analytics\Admin\EntityPageLinks;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsEntityQueryRequest;
use App\Models\StoreProfile;
use Illuminate\View\View;

/**
 * `/admin/analytics/stores/{store}`, the drill-in's one-store page:
 * identity, range tiles, a daily strip, and the event feed.
 * `App\Analytics\Admin\EntityActivity` does the read; this assembles it
 * with the page's own segmented controls and action links.
 */
final class StoreController extends Controller
{
    private const string ROUTE_NAME = 'admin.analytics.stores.show';

    private const string PARAM_KEY = 'store';

    public function show(StoreProfile $store, AnalyticsEntityQueryRequest $request): View
    {
        $roundTripped = $request->roundTripped();
        $rangeDays = $request->rangeDays();
        $filter = $request->eventFilter();

        $range = AnalyticsRange::of($rangeDays, $this->now());
        $activity = EntityActivity::forStore($store, $range, $filter);

        return view('admin.analytics.entities.show', [
            'activity' => $activity,
            'now' => $this->now(),
            'rangeCaption' => $range->caption(),
            'rangeLinks' => EntityPageLinks::range(self::ROUTE_NAME, self::PARAM_KEY, $store->id, $roundTripped, $rangeDays),
            'eventLinks' => EntityPageLinks::event(self::ROUTE_NAME, self::PARAM_KEY, $store->id, $roundTripped, $filter, AnalyticsEventName::forSubject('store')),
            'backHref' => route('admin.analytics.index', array_intersect_key($roundTripped, ['range' => true])),
            'backLabel' => 'Analytics',
            'actions' => $this->actions($store),
        ]);
    }

    /**
     * The identity card's action column: open the seller this store
     * belongs to.
     *
     * @return list<array{label: string, href: string, variant: string}>
     */
    private function actions(StoreProfile $store): array
    {
        return [
            ['label' => 'Open seller', 'href' => route('admin.sellers.show', $store->seller), 'variant' => 'primary'],
        ];
    }
}
