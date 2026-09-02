<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Admin\ActorList;
use App\Domain\Analytics\ActorKindFilter;
use App\Domain\Analytics\ActorSort;
use App\Domain\Analytics\AnalyticsRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsActorsQueryRequest;
use Illuminate\View\View;

/**
 * `/admin/analytics/actors`, the drill-in's all-actors page: every actor
 * that carried an event in the range, sorted by most active or most
 * recent and paged. `App\Analytics\Admin\ActorList` does the query; this
 * assembles its result and the page's segmented controls into one view.
 */
final class ActorController extends Controller
{
    /** Rows per page — small enough that a handful of seeded actors pages
     * over more than one screen. */
    public const int PER_PAGE = 25;

    private const array RANGE_LABELS = [7 => '7d', 30 => '30d', 90 => '90d'];

    private const array SORT_LABELS = [
        'active' => 'Most active',
        'recent' => 'Most recent',
    ];

    private const array ACTOR_KIND_LABELS = [
        ActorKindFilter::All->value => 'All',
        ActorKindFilter::Anonymous->value => 'Anonymous',
        ActorKindFilter::Verified->value => 'Verified',
    ];

    public function index(AnalyticsActorsQueryRequest $request): View
    {
        $roundTripped = $request->roundTripped();
        $rangeDays = $request->rangeDays();
        $sort = $request->sort();
        $actorKind = $request->actorKind();
        $search = $request->search();

        $now = $this->now();
        $range = AnalyticsRange::of($rangeDays, $now);
        $actorsPage = ActorList::forRange($range, $sort, $actorKind, $search, $request->page(), self::PER_PAGE);

        return view('admin.analytics.actors.index', [
            'now' => $now,
            'rangeCaption' => $range->caption(),
            'rows' => $actorsPage->rows,
            'page' => $actorsPage->page,
            'search' => $search ?? '',
            'roundTripped' => $roundTripped,
            'rangeLinks' => $this->rangeLinks($roundTripped, $rangeDays),
            'sortLinks' => $this->sortLinks($roundTripped, $sort),
            'actorFilterLinks' => $this->actorFilterLinks($roundTripped, $actorKind),
            'indexHref' => route('admin.analytics.index', collect($roundTripped)->except('sort')->all()),
            'filterQuery' => http_build_query($roundTripped),
        ]);
    }

    /**
     * The range segmented control: one link per {@see AnalyticsRange::SIZES},
     * every other filter carried through unchanged, `page` reset.
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
                'href' => route('admin.analytics.actors.index', [...$without, 'range' => $days]),
                'active' => $days === $current,
            ],
            AnalyticsRange::SIZES,
        );
    }

    /**
     * The sort segmented control: one link per {@see ActorSort} case,
     * every other filter carried through unchanged, `page` reset.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function sortLinks(array $roundTripped, ActorSort $current): array
    {
        $without = collect($roundTripped)->except('sort')->all();

        return array_map(
            fn (ActorSort $sort): array => [
                'label' => self::SORT_LABELS[$sort->value],
                'href' => route('admin.analytics.actors.index', [...$without, 'sort' => $sort->value]),
                'active' => $sort === $current,
            ],
            ActorSort::cases(),
        );
    }

    /**
     * The actor-kind segmented control: one link per {@see ActorKindFilter}
     * case, every other filter carried through unchanged, `page` reset.
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
                'href' => route('admin.analytics.actors.index', [...$without, 'actors' => $kind->value]),
                'active' => $kind === $current,
            ],
            ActorKindFilter::cases(),
        );
    }
}
