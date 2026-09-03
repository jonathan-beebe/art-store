<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Admin\Funnel as FunnelQuery;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\FunnelDefinition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsQueryRequest;
use App\Models\Funnel;
use Illuminate\View\View;

/**
 * `/admin/analytics/funnels/{funnel}`, one admin-defined funnel's detail
 * page: the range control and the funnel's own steps, drawn by the same
 * `x-admin.analytics.funnel` component every other funnel mount uses.
 */
final class FunnelController extends Controller
{
    private const array RANGE_LABELS = [7 => '7d', 30 => '30d', 90 => '90d'];

    public function show(Funnel $funnel, AnalyticsQueryRequest $request): View
    {
        $rangeDays = $request->rangeDays();
        $range = AnalyticsRange::of($rangeDays, $this->now());

        return view('admin.analytics.funnels.show', [
            'funnel' => $funnel,
            'funnelView' => FunnelQuery::forRange(FunnelDefinition::of($funnel->steps), $range),
            'rangeDays' => $rangeDays,
            'rangeCaption' => $range->caption(),
            'rangeLinks' => $this->rangeLinks($funnel, $rangeDays),
            'stepChain' => self::stepChain($funnel),
        ]);
    }

    /**
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function rangeLinks(Funnel $funnel, int $current): array
    {
        return array_map(
            fn (int $days): array => [
                'label' => self::RANGE_LABELS[$days],
                'href' => route('admin.analytics.funnels.show', ['funnel' => $funnel, 'range' => $days]),
                'active' => $days === $current,
            ],
            AnalyticsRange::SIZES,
        );
    }

    /**
     * @return list<string>
     */
    private static function stepChain(Funnel $funnel): array
    {
        return array_map(fn (AnalyticsEventName $name): string => $name->pluralLabel(), $funnel->steps());
    }
}
