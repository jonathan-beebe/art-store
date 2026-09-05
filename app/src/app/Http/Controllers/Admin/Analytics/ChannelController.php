<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Admin\ChannelTable;
use App\Analytics\Admin\ChannelVisits;
use App\Domain\Analytics\AnalyticsRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsChannelsQueryRequest;
use App\Http\Requests\Admin\AnalyticsChannelVisitsQueryRequest;
use Illuminate\View\View;

/**
 * `/admin/analytics/channels`, the drill-in's channel page, and
 * `/admin/analytics/channels/{key}`, one channel's own visits.
 * `App\Analytics\Admin\ChannelTable` and `App\Analytics\Admin\ChannelVisits`
 * do the reads; this assembles them with the pages' own range control.
 */
final class ChannelController extends Controller
{
    /** Rows per page on the visits drill-in — the same size the all-actors
     * page pages by. */
    public const int PER_PAGE = 25;

    private const array RANGE_LABELS = [7 => '7d', 30 => '30d', 90 => '90d'];

    public function index(AnalyticsChannelsQueryRequest $request): View
    {
        $roundTripped = $request->roundTripped();
        $rangeDays = $request->rangeDays();

        $range = AnalyticsRange::of($rangeDays, $this->now());
        $channels = ChannelTable::forRange($range);

        return view('admin.analytics.channels.index', [
            'channels' => $channels,
            'rangeCaption' => $range->caption(),
            'rangeLinks' => $this->rangeLinks($roundTripped, $rangeDays),
            'indexHref' => route('admin.analytics.index', $roundTripped),
            'roundTripped' => $roundTripped,
        ]);
    }

    public function show(string $key, AnalyticsChannelVisitsQueryRequest $request): View
    {
        $roundTripped = $request->roundTripped();
        $rangeDays = $request->rangeDays();

        $range = AnalyticsRange::of($rangeDays, $this->now());
        $visits = ChannelVisits::forRange($range, $key, $request->page(), self::PER_PAGE);

        if ($visits === null) {
            abort(404);
        }

        return view('admin.analytics.channels.show', [
            'label' => $visits->label,
            'channelKey' => $key,
            'page' => $visits->page,
            'rows' => $visits->rows,
            'rangeCaption' => $range->caption(),
            'rangeLinks' => $this->visitsRangeLinks($key, $roundTripped, $rangeDays),
            'backHref' => route('admin.analytics.channels.index', $roundTripped),
            'filterQuery' => http_build_query($roundTripped),
        ]);
    }

    /**
     * The channel page's range segmented control: one link per
     * {@see AnalyticsRange::SIZES}.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function rangeLinks(array $roundTripped, int $current): array
    {
        $without = collect($roundTripped)->except('range')->all();

        return array_map(
            fn (int $days): array => [
                'label' => self::RANGE_LABELS[$days],
                'href' => route('admin.analytics.channels.index', [...$without, 'range' => $days]),
                'active' => $days === $current,
            ],
            AnalyticsRange::SIZES,
        );
    }

    /**
     * The visits drill-in's range segmented control: `page` reset.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function visitsRangeLinks(string $key, array $roundTripped, int $current): array
    {
        $without = collect($roundTripped)->except('range')->all();

        return array_map(
            fn (int $days): array => [
                'label' => self::RANGE_LABELS[$days],
                'href' => route('admin.analytics.channels.show', ['key' => $key, ...$without, 'range' => $days]),
                'active' => $days === $current,
            ],
            AnalyticsRange::SIZES,
        );
    }
}
