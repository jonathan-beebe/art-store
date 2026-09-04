<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\GenerateVariants;
use App\Actions\Listings\CreateListing;
use App\Actions\Listings\UpdateListing;
use App\Analytics\AnalyticsReport;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\BarStrip;
use App\Domain\Analytics\BarStripBar;
use App\Domain\Configurator\PricingMode;
use App\Domain\Listings\ListingCreationShape;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Domain\Seller\ListingTableRow;
use App\Domain\Seller\ListingView;
use App\Domain\Seller\RowSort;
use App\Http\Requests\Seller\ListingCreateRequest;
use App\Http\Requests\Seller\ListingRequest;
use App\Http\Requests\Seller\ListingsQueryRequest;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\OrderItem;
use App\Seller\ListingsChrome;
use App\Seller\ListingTable;
use App\Support\Configurator\ListingBasicsPageData;
use App\Support\Configurator\ListingEditPageData;
use App\Support\ListPaneWindow;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class ListingController extends SellerController
{
    private const int ACTIVITY_STRIP_HEIGHT_PX = 72;

    /** Listings are evergreen; every ranged figure on the tool reads this fixed window. */
    private const int ANALYTICS_WINDOW_DAYS = 30;

    public function index(ListingsQueryRequest $request): View
    {
        $view = $request->view();
        $sort = $request->sort();
        $roundTripped = $request->roundTripped();
        $chrome = ListingsChrome::build($roundTripped, $view, $sort);

        if ($view === ListingView::List) {
            $window = ListPaneWindow::of($this->listingsQuery());

            return view('seller.listings.index', [
                'chrome' => $chrome,
                'listings' => $window->items,
                'listingsTotal' => $window->total,
            ]);
        }

        $range = AnalyticsRange::of(self::ANALYTICS_WINDOW_DAYS, $this->now());
        $rows = RowSort::apply($sort, ListingTable::forSeller($this->seller(), $range), fn (ListingTableRow $row): string => $row->id);

        return view('seller.listings.index', [
            'chrome' => $chrome,
            'rows' => $rows,
            'listingsTotal' => count($rows),
            'rangeDays' => $range->days,
        ]);
    }

    /**
     * The question screen with no params; Continue submits back here by GET
     * with `title` and `shape`, which renders that shape's landing screen
     * instead — the same route both ways, so a shape typed into the address
     * bar (or bookmarked) reopens exactly where Continue left off.
     */
    public function create(ListingCreateRequest $request): View
    {
        $shape = $request->shape();
        $title = $request->title();

        if ($shape !== null && $title !== null) {
            return view($this->createView($shape), ['title' => $title]);
        }

        return view('seller.listings.create');
    }

    public function store(
        ListingRequest $request,
        CreateListing $createListing,
        CreateOptionAxis $createOptionAxis,
        AddOptionValue $addOptionValue,
        GenerateVariants $generateVariants,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, $this->createView($request->shape()), [
                'title' => $request->input('title', ''),
            ]);
        }

        $listing = $createListing($this->seller(), $request->toDraft());

        match ($request->shape()) {
            ListingCreationShape::OneThing => null,
            ListingCreationShape::Versions => $this->addVersionsAxis($listing, $request, $createOptionAxis, $addOptionValue, $generateVariants),
            ListingCreationShape::Extras => $this->addExtraAxisIfAny($listing, $request, $createOptionAxis, $addOptionValue, $generateVariants),
        };

        return redirect()
            ->route('seller.listings.edit', $listing)
            ->with('status', "\"{$listing->title}\" is saved as a draft.");
    }

    public function show(Listing $listing, ListingsQueryRequest $request): View
    {
        $this->authorize('view', $listing);

        $from = $request->from();
        $view = $from ?? ListingView::List;
        $sort = $request->sort();
        $range = AnalyticsRange::of(self::ANALYTICS_WINDOW_DAYS, $this->now());

        if ($from === null) {
            $chrome = ListingsChrome::build($request->roundTripped(), $view, $sort);
            $detail = $this->detailData($listing, $range);

            // DSGN-006: the show route's list pane is the same default,
            // unfiltered list the index route opens with, mirroring the
            // admin listings pane (App\Http\Controllers\Admin\ListingController).
            $window = ListPaneWindow::of($this->listingsQuery(), $listing);

            return view('seller.listings.show', [
                ...$detail,
                'chrome' => $chrome,
                'listingsTotal' => $window->total,
                'cellListings' => $window->items,
                'cellListingsTotal' => $window->total,
            ]);
        }

        // The detail route carries `from`, not `view`; every link the
        // header and the workspace behind the overlay build needs `view`
        // named explicitly, so switching mode or sort from here still
        // lands on the right one.
        $roundTripped = [...$request->roundTripped(), 'view' => $view->value];
        $chrome = ListingsChrome::build($roundTripped, $view, $sort);

        // One read of the seller's rows serves both the workspace behind
        // the overlay and the opened listing's own detail, so a listing's
        // sold, revenue, and ranged counts are read once, not twice.
        $rows = RowSort::apply($sort, ListingTable::forSeller($this->seller(), $range), fn (ListingTableRow $row): string => $row->id);
        $detail = $this->detailData($listing, $range, self::rowFor($rows, $listing, $range));

        return view('seller.listings.detail-overlay', [
            ...$detail,
            'chrome' => $chrome,
            'rows' => $rows,
            'listingsTotal' => count($rows),
            'rangeDays' => $range->days,
            'backHref' => route('seller.listings.index', $roundTripped),
        ]);
    }

    public function edit(Listing $listing): View
    {
        $this->authorize('update', $listing);

        return view('seller.listings.edit', ListingEditPageData::for($listing));
    }

    public function update(ListingRequest $request, Listing $listing, UpdateListing $updateListing, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.basics.edit', ListingBasicsPageData::for($listing));
        }

        $updated = $updateListing($listing, $request->toDraft());

        return redirect()
            ->route('seller.listings.basics.edit', $updated)
            ->with('status', "\"{$updated->title}\" is updated.");
    }

    /**
     * @return view-string
     */
    private function createView(ListingCreationShape $shape): string
    {
        return match ($shape) {
            ListingCreationShape::OneThing => 'seller.listings.create.one',
            ListingCreationShape::Versions => 'seller.listings.create.versions',
            ListingCreationShape::Extras => 'seller.listings.create.extras',
        };
    }

    /**
     * The versions ramp: a standalone choice, one option per version row —
     * every version prices itself, so there is no base price to carry over.
     * The first row is the default, which is what {@see \App\Support\Configurator\ListingPriceSync}
     * reads back onto `listings.price_cents`.
     */
    private function addVersionsAxis(
        Listing $listing,
        ListingRequest $request,
        CreateOptionAxis $createOptionAxis,
        AddOptionValue $addOptionValue,
        GenerateVariants $generateVariants,
    ): void {
        $axis = $createOptionAxis($listing, $request->choiceName(), null, 0, PricingMode::Standalone);

        $this->addRows($axis, $request->versionRows(), $addOptionValue);

        $generateVariants($listing);
    }

    /**
     * The extras ramp's optional first choice: nothing to do when the seller
     * skipped it (the "Create with just the price" link, or leaving both the
     * name and every row blank) — the listing stays a plain, axis-free draft.
     */
    private function addExtraAxisIfAny(
        Listing $listing,
        ListingRequest $request,
        CreateOptionAxis $createOptionAxis,
        AddOptionValue $addOptionValue,
        GenerateVariants $generateVariants,
    ): void {
        $rows = $request->extraOptionRows();

        if ($rows === []) {
            return;
        }

        $axis = $createOptionAxis($listing, $request->extraChoiceName(), null, 0, PricingMode::AddOn);

        $this->addRows($axis, $rows, $addOptionValue);

        $generateVariants($listing);
    }

    /**
     * @param  list<array{label: string, cents: int}>  $rows
     */
    private function addRows(OptionAxis $axis, array $rows, AddOptionValue $addOptionValue): void
    {
        foreach ($rows as $index => $row) {
            $addOptionValue($axis, $row['label'], $row['cents'], $index === 0, $index, null, $axis->pricing_mode === PricingMode::Standalone ? $row['cents'] : null);
        }
    }

    /**
     * One listing's detail: the row every table and grid render it as, its
     * full sales list, and its ranged daily view strip. `$row` is the same
     * {@see ListingTableRow} a table or grid already read this listing as,
     * when the caller has one; omitted, it reads its own — the list pane's
     * detail, which has no table of rows to read one from.
     *
     * @return array{listing: Listing, row: ListingTableRow, sales: Collection<int, OrderItem>, strip: list<BarStripBar>, rangeDays: int}
     */
    private function detailData(Listing $listing, AnalyticsRange $range, ?ListingTableRow $row = null): array
    {
        $listing->load(['activeRemoval', 'category', 'images']);

        $daily = AnalyticsReport::dailyCountsForListingSince($listing->id, $range->start);
        $dayLabels = $range->dayLabels();
        $viewCounts = array_map(
            fn (string $day): int => $daily[$day][AnalyticsEventName::ListingView->value] ?? 0,
            $dayLabels,
        );

        return [
            'listing' => $listing,
            'row' => $row ?? ListingTable::forListing($listing, $range),
            'sales' => $this->sales($listing),
            'strip' => BarStrip::bars($viewCounts, $dayLabels, self::ACTIVITY_STRIP_HEIGHT_PX),
            'rangeDays' => $range->days,
        ];
    }

    /**
     * `$listing`'s own row out of `$rows`, when it is there — a fresh read
     * of it otherwise, which a listing outside the seller's own rows
     * should never need in practice.
     *
     * @param  list<ListingTableRow>  $rows
     */
    private static function rowFor(array $rows, Listing $listing, AnalyticsRange $range): ListingTableRow
    {
        foreach ($rows as $row) {
            if ($row->id === $listing->id) {
                return $row;
            }
        }

        return ListingTable::forListing($listing, $range);
    }

    /**
     * @return Collection<int, OrderItem>
     */
    private function sales(Listing $listing): Collection
    {
        return $listing->orderItems()
            ->with('order')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The seller's own listings, newest first — the list pane's source for
     * both the index route and a show route's pane (DSGN-006). Carries the
     * `images` eager load the pane's thumbnails need and the
     * `activeRemoval` eager load its status badge needs.
     *
     * @return Builder<Listing>
     */
    private function listingsQuery(): Builder
    {
        return Listing::query()
            ->ofSeller($this->seller()->id)
            ->with([
                'activeRemoval',
                'images' => fn (Relation $images): Relation => $images->orderBy('position'),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
