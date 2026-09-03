<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Admin\ActorList;
use App\Analytics\Admin\EntityActivity;
use App\Domain\Analytics\ActorKindFilter;
use App\Domain\Analytics\ActorSort;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsActorsQueryRequest;
use App\Http\Requests\Admin\AnalyticsEntityQueryRequest;
use App\Models\Customer;
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

    /** The entry and event pages' own copy for each event name — see
     * {@see \App\Analytics\Admin\EventTotals::EVENT_LABELS}, duplicated here
     * the same way every page that shows this vocabulary keeps its own
     * copy of it. */
    private const array EVENT_LABELS = [
        'listing.view' => 'Listing views',
        'listing.favorite' => 'Favorites',
        'listing.unfavorite' => 'Unfavorites',
        'listing.cart_add' => 'Cart adds',
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
     * `/admin/analytics/actors/{customer}`, the drill-in's one-actor page:
     * identity, range tiles, a daily or (once flagged) hourly strip, and the
     * event feed. `App\Analytics\Admin\EntityActivity` does the read; this
     * assembles it with the page's own segmented controls and action links.
     */
    public function show(Customer $customer, AnalyticsEntityQueryRequest $request): View
    {
        $roundTripped = $request->roundTripped();
        $rangeDays = $request->rangeDays();
        $filter = $request->eventFilter();

        $now = $this->now();
        $range = AnalyticsRange::of($rangeDays, $now);
        $activity = EntityActivity::forActor($customer, $range, $filter, $now);

        return view('admin.analytics.entities.show', [
            'activity' => $activity,
            'now' => $now,
            'rangeCaption' => $range->caption(),
            'rangeLinks' => $this->entityRangeLinks($customer->id, $roundTripped, $rangeDays),
            'eventLinks' => $this->entityEventLinks($customer->id, $roundTripped, $filter),
            'backHref' => route('admin.analytics.index', array_intersect_key($roundTripped, ['range' => true])),
            'backLabel' => 'Analytics',
            'actions' => $this->actions($customer),
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

    /**
     * The actor page's range segmented control: `event` carried through
     * unchanged.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function entityRangeLinks(string $customerId, array $roundTripped, int $current): array
    {
        $without = collect($roundTripped)->except('range')->all();

        return array_map(
            fn (int $days): array => [
                'label' => self::RANGE_LABELS[$days],
                'href' => route('admin.analytics.actors.show', ['customer' => $customerId, ...$without, 'range' => $days]),
                'active' => $days === $current,
            ],
            AnalyticsRange::SIZES,
        );
    }

    /**
     * The actor page's event-name segmented control: "All" plus one link
     * per {@see AnalyticsEventName} case, `range` carried through unchanged.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function entityEventLinks(string $customerId, array $roundTripped, ?AnalyticsEventName $current): array
    {
        $without = collect($roundTripped)->except('event')->all();

        $links = [[
            'label' => 'All',
            'href' => route('admin.analytics.actors.show', ['customer' => $customerId, ...$without]),
            'active' => $current === null,
        ]];

        foreach (AnalyticsEventName::cases() as $name) {
            $links[] = [
                'label' => self::EVENT_LABELS[$name->value],
                'href' => route('admin.analytics.actors.show', ['customer' => $customerId, ...$without, 'event' => $name->value]),
                'active' => $current === $name,
            ];
        }

        return $links;
    }

    /**
     * The identity card's action column: open the customer, open the log
     * viewer filtered to this actor, and a link to the customer page's own
     * block form — the block flow itself lives only there.
     *
     * @return list<array{label: string, href: string, variant: string}>
     */
    private function actions(Customer $customer): array
    {
        return [
            ['label' => 'Open customer', 'href' => route('admin.customers.show', $customer), 'variant' => 'primary'],
            ['label' => 'Open in logs', 'href' => route('admin.logs.index', ['actor' => $customer->id]), 'variant' => 'secondary'],
            ['label' => 'Block customer', 'href' => route('admin.customers.show', $customer).'#standing-heading', 'variant' => 'danger'],
        ];
    }
}
