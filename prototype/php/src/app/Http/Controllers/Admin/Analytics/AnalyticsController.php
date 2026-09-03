<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Admin\ActorLeaderboard;
use App\Analytics\Admin\AnalyticsJump;
use App\Analytics\Admin\ChannelRow;
use App\Analytics\Admin\ChannelTable;
use App\Analytics\Admin\EventTotals;
use App\Analytics\Admin\Funnel;
use App\Domain\Analytics\ActorKindFilter;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\FunnelDefinition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsQueryRequest;
use Illuminate\View\View;

/**
 * `/admin/analytics`, the drill-in's entry page: every event name compared
 * against the range before it, and the actors carrying the most events per
 * hour. docs/analytics.md and `App\Analytics\Admin` hold the reads this
 * assembles into one view.
 */
final class AnalyticsController extends Controller
{
    /** How many rows the entry page's leaderboard shows — the all-actors
     * page (a later stage) is where the rest of the list lives. */
    private const int LEADERBOARD_LIMIT = 6;

    /** How many channels the entry page's summary line names — the
     * channels page is where the rest of the table lives. */
    private const int CHANNELS_SUMMARY_LIMIT = 3;

    private const array RANGE_LABELS = [7 => '7d', 30 => '30d', 90 => '90d'];

    private const array ACTOR_KIND_LABELS = [
        ActorKindFilter::All->value => 'All',
        ActorKindFilter::Anonymous->value => 'Anonymous',
        ActorKindFilter::Verified->value => 'Verified',
    ];

    public function index(AnalyticsQueryRequest $request): View
    {
        $roundTripped = $request->roundTripped();
        $rangeDays = $request->rangeDays();
        $actorKind = $request->actorKind();
        $search = $request->search();

        $now = $this->now();
        $range = AnalyticsRange::of($rangeDays, $now);
        $channels = ChannelTable::forRange($range);

        return view('admin.analytics.index', [
            'now' => $now,
            'rangeCaption' => $range->caption(),
            'dayLabels' => $range->dayLabels(),
            'funnel' => Funnel::forRange(FunnelDefinition::storefront(), $range),
            'events' => EventTotals::forRange($range, $search),
            'actors' => ActorLeaderboard::forRange($range, $actorKind, $search, self::LEADERBOARD_LIMIT),
            'channelsSummary' => $this->channelsSummary($channels),
            'jump' => $search === null || $search === '' ? null : AnalyticsJump::for($search),
            'rangeLinks' => $this->rangeLinks($roundTripped, $rangeDays),
            'actorFilterLinks' => $this->actorFilterLinks($roundTripped, $actorKind),
            'search' => $search ?? '',
            'roundTripped' => $roundTripped,
            'allActorsHref' => route('admin.analytics.actors.index', $roundTripped),
            'allChannelsHref' => route('admin.analytics.channels.index', array_intersect_key($roundTripped, ['range' => true])),
        ]);
    }

    /**
     * "Direct (120) · Google search (45) · Instagram (12)" — the top
     * channels by visitors this range, the channels section's own summary
     * line.
     *
     * @param  list<ChannelRow>  $channels
     */
    private function channelsSummary(array $channels): string
    {
        $top = array_slice($channels, 0, self::CHANNELS_SUMMARY_LIMIT);

        if ($top === []) {
            return 'No channel activity in this range.';
        }

        return collect($top)
            ->map(fn (ChannelRow $row): string => "{$row->label} (".number_format($row->visitors->current).')')
            ->implode(' · ');
    }

    /**
     * The range segmented control: one link per {@see AnalyticsRange::SIZES},
     * every other filter carried through unchanged.
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
                'href' => route('admin.analytics.index', [...$without, 'range' => $days]),
                'active' => $days === $current,
            ],
            AnalyticsRange::SIZES,
        );
    }

    /**
     * The actor-kind segmented control: one link per {@see ActorKindFilter}
     * case, every other filter carried through unchanged.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function actorFilterLinks(array $roundTripped, ActorKindFilter $current): array
    {
        $without = collect($roundTripped)->except('actors')->all();

        return array_map(
            fn (ActorKindFilter $kind): array => [
                'label' => self::ACTOR_KIND_LABELS[$kind->value],
                'href' => route('admin.analytics.index', [...$without, 'actors' => $kind->value]),
                'active' => $kind === $current,
            ],
            ActorKindFilter::cases(),
        );
    }
}
