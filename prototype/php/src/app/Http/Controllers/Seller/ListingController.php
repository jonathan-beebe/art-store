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
use App\Domain\Seller\ListingSort;
use App\Domain\Seller\ListingSortColumn;
use App\Domain\Seller\ListingTableRow;
use App\Domain\Seller\ListingTableSort;
use App\Domain\Seller\ListingView;
use App\Http\Requests\Seller\ListingRequest;
use App\Http\Requests\Seller\ListingsQueryRequest;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\OrderItem;
use App\Seller\ListingTable;
use App\Support\Configurator\ListingBasicsPageData;
use App\Support\Configurator\ListingEditPageData;
use App\Support\ListPaneWindow;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class ListingController extends SellerController
{
    private const int ACTIVITY_STRIP_HEIGHT_PX = 72;

    public function index(ListingsQueryRequest $request): View
    {
        $view = $request->view();
        $sort = $request->sort();
        $roundTripped = $request->roundTripped();

        if ($view === ListingView::List) {
            $window = ListPaneWindow::of($this->listingsQuery());

            return view('seller.listings.index', [
                'listings' => $window->items,
                'listingsTotal' => $window->total,
                ...$this->workspaceChrome($roundTripped, $view, $sort),
            ]);
        }

        $range = AnalyticsRange::of($request->rangeDays(), $this->now());

        return view('seller.listings.index', [
            ...$this->workspaceChrome($roundTripped, $view, $sort),
            ...$this->tableData($roundTripped, $sort, $range),
        ]);
    }

    /**
     * The question screen with no params; Continue submits back here by GET
     * with `title` and `shape`, which renders that shape's landing screen
     * instead — the same route both ways, so a shape typed into the address
     * bar (or bookmarked) reopens exactly where Continue left off.
     */
    public function create(Request $request): View
    {
        $shape = ListingCreationShape::tryFrom((string) $request->query('shape'));
        $title = $request->query('title');

        if ($shape !== null && is_string($title)) {
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
        $view = $from === null ? ListingView::List : ListingView::from($from);
        $sort = $request->sort();
        $range = AnalyticsRange::of($request->rangeDays(), $this->now());
        $detail = $this->detailData($listing, $range);
        // The detail route carries `from`, not `view`; every link the
        // header and, on table/grid, the workspace behind the overlay
        // build needs `view` named explicitly, so switching mode or sort
        // from here still lands on the right one.
        $roundTripped = [...$request->roundTripped(), 'view' => $view->value];

        if ($from === null) {
            // DSGN-006: the show route's list pane is the same default,
            // unfiltered list the index route opens with, mirroring the
            // admin listings pane (App\Http\Controllers\Admin\ListingController).
            $window = ListPaneWindow::of($this->listingsQuery(), $listing);

            return view('seller.listings.show', [
                ...$detail,
                ...$this->workspaceChrome($roundTripped, $view, $sort),
                'listingsTotal' => $window->total,
                'cellListings' => $window->items,
                'cellListingsTotal' => $window->total,
            ]);
        }

        return view('seller.listings.detail-overlay', [
            ...$detail,
            ...$this->workspaceChrome($roundTripped, $view, $sort),
            ...$this->tableData($roundTripped, $sort, $range),
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
     * The header every listings view shares: the view switch, and, on
     * table and grid, the sort select. The select posts back to the index
     * route by GET, carrying every other current filter as a hidden
     * field. `$roundTripped` always names the view being rendered — the
     * request's own {@see ListingsQueryRequest::roundTripped()} on the
     * index route, that plus the view `from` resolved to on the detail
     * route, since a detail route carries `from` rather than `view`.
     *
     * @param  array<string, string>  $roundTripped
     * @return array{view: ListingView, viewLinks: list<array{key: string, label: string, href: string, active: bool}>, sort: ListingSort, sortOptions: list<array{value: string, label: string, selected: bool}>, sortFormFields: array<string, string>}
     */
    private function workspaceChrome(array $roundTripped, ListingView $view, ListingSort $sort): array
    {
        return [
            'view' => $view,
            'viewLinks' => $this->viewLinks($roundTripped, $view),
            'sort' => $sort,
            'sortOptions' => $this->sortOptions($sort),
            'sortFormFields' => collect($roundTripped)->except(['sort', 'dir'])->all(),
        ];
    }

    /**
     * @param  array<string, string>  $roundTripped
     * @return list<array{key: string, label: string, href: string, active: bool}>
     */
    private function viewLinks(array $roundTripped, ListingView $current): array
    {
        $without = collect($roundTripped)->except('view')->all();

        return array_map(fn (ListingView $view): array => [
            'key' => $view->value,
            'label' => $view->label(),
            'href' => route('seller.listings.index', [...$without, 'view' => $view->value]),
            'active' => $view === $current,
        ], ListingView::cases());
    }

    /**
     * The header's sort `<select>`: every column but Status, which the
     * table's own header link already sorts. Picking one always sorts
     * descending, the same as clicking a header that was not already
     * sorted.
     *
     * @return list<array{value: string, label: string, selected: bool}>
     */
    private function sortOptions(ListingSort $sort): array
    {
        return array_values(array_map(fn (ListingSortColumn $column): array => [
            'value' => $column->value,
            'label' => $column->label(),
            'selected' => $sort->isColumn($column),
        ], array_filter(ListingSortColumn::cases(), fn (ListingSortColumn $column): bool => $column !== ListingSortColumn::Status)));
    }

    /**
     * The table's own sortable column headers: `aria-sort`, and a link that
     * flips the direction when the header is already the sorted column.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<array{column: ListingSortColumn, label: string, alignsRight: bool, ariaSort: string, href: string}>
     */
    private function columnLinks(array $roundTripped, ListingSort $sort): array
    {
        $without = collect($roundTripped)->except(['sort', 'dir'])->all();

        return array_map(fn (ListingSortColumn $column): array => [
            'column' => $column,
            'label' => $column->label(),
            'alignsRight' => $column->alignsRight(),
            'ariaSort' => $sort->ariaSort($column) ?? 'none',
            'href' => route('seller.listings.index', [...$without, 'sort' => $column->value, 'dir' => $sort->nextDirectionFor($column)->value]),
        ], ListingSortColumn::cases());
    }

    /**
     * The table and grid's rows, sorted, plus the column headers that sort
     * them.
     *
     * @param  array<string, string>  $roundTripped
     * @return array{rows: list<ListingTableRow>, listingsTotal: int, columnLinks: list<array{column: ListingSortColumn, label: string, alignsRight: bool, ariaSort: string, href: string}>, rangeDays: int}
     */
    private function tableData(array $roundTripped, ListingSort $sort, AnalyticsRange $range): array
    {
        $rows = ListingTableSort::apply($sort, ListingTable::forSeller($this->seller(), $range));

        return [
            'rows' => $rows,
            'listingsTotal' => count($rows),
            'columnLinks' => $this->columnLinks($roundTripped, $sort),
            'rangeDays' => $range->days,
        ];
    }

    /**
     * One listing's detail: the row every table and grid render it as, its
     * full sales list, and its ranged daily view strip.
     *
     * @return array{listing: Listing, row: ListingTableRow, sales: Collection<int, OrderItem>, strip: list<BarStripBar>, rangeDays: int}
     */
    private function detailData(Listing $listing, AnalyticsRange $range): array
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
            'row' => ListingTable::forListing($listing, $range),
            'sales' => $this->sales($listing),
            'strip' => BarStrip::bars($viewCounts, $dayLabels, self::ACTIVITY_STRIP_HEIGHT_PX),
            'rangeDays' => $range->days,
        ];
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
