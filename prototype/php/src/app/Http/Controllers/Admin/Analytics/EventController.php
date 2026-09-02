<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Admin\EventDetail;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\EventBreakdown;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsEventQueryRequest;
use Illuminate\View\View;

/**
 * `/admin/analytics/events/{name}`, the drill-in's event page: one event
 * name's range tiles, daily bars, and a breakdown by listing, actor, or —
 * for `page.view` — route pattern. `{name}` names neither an Eloquent model
 * nor a route-bound enum (`page.view` is not an {@see AnalyticsEventName}
 * case), so this controller checks it against the closed vocabulary itself
 * and 404s on anything else.
 */
final class EventController extends Controller
{
    private const array RANGE_LABELS = [7 => '7d', 30 => '30d', 90 => '90d'];

    public function show(string $name, AnalyticsEventQueryRequest $request): View
    {
        if (AnalyticsEventName::tryFrom($name) === null && $name !== EventBreakdown::PAGE_VIEW_EVENT_NAME) {
            abort(404);
        }

        $roundTripped = $request->roundTripped();
        $rangeDays = $request->rangeDays();
        $by = $request->breakdown();

        $range = AnalyticsRange::of($rangeDays, $this->now());
        $detail = EventDetail::forRange($name, $range, $by);

        return view('admin.analytics.events.show', [
            'detail' => $detail,
            'rangeCaption' => $range->caption(),
            'dayLabels' => $range->dayLabels(),
            'rangeLinks' => $this->rangeLinks($name, $roundTripped, $rangeDays),
            'breakdownLinks' => $this->breakdownLinks($name, $roundTripped, $by),
            'indexHref' => route('admin.analytics.index', array_intersect_key($roundTripped, ['range' => true])),
        ]);
    }

    /**
     * The range segmented control: one link per {@see AnalyticsRange::SIZES},
     * `by` carried through unchanged.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function rangeLinks(string $name, array $roundTripped, int $current): array
    {
        $without = collect($roundTripped)->except('range')->all();

        return array_map(
            fn (int $days): array => [
                'label' => self::RANGE_LABELS[$days],
                'href' => route('admin.analytics.events.show', ['name' => $name, ...$without, 'range' => $days]),
                'active' => $days === $current,
            ],
            AnalyticsRange::SIZES,
        );
    }

    /**
     * The breakdown segmented control: one link per breakdown this event
     * name allows, `range` carried through unchanged. A single-entry list
     * (`page.view`, which allows only `pattern`) is the view's cue to hide
     * the control, since it would have nothing to switch.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function breakdownLinks(string $name, array $roundTripped, EventBreakdown $current): array
    {
        $without = collect($roundTripped)->except('by')->all();

        return array_map(
            fn (EventBreakdown $breakdown): array => [
                'label' => $breakdown->heading(),
                'href' => route('admin.analytics.events.show', ['name' => $name, ...$without, 'by' => $breakdown->value]),
                'active' => $breakdown === $current,
            ],
            EventBreakdown::allowedFor($name),
        );
    }
}
