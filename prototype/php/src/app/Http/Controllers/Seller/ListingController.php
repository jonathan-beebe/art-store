<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\GenerateVariants;
use App\Actions\Listings\CreateListing;
use App\Actions\Listings\UpdateListing;
use App\Domain\Configurator\PricingMode;
use App\Domain\Listings\ListingCreationShape;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Domain\Reports\ActivityTimeline;
use App\Http\Requests\Seller\ListingRequest;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\OrderItem;
use App\Support\Configurator\ListingBasicsPageData;
use App\Support\Configurator\ListingEditPageData;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class ListingController extends SellerController
{
    private const ACTIVITY_WINDOW_DAYS = 14;

    public function index(): View
    {
        return view('seller.listings.index', [
            'listings' => $this->seller()->listings()->withEventCounts()->with('activeRemoval')->orderByDesc('created_at')->orderByDesc('id')->get(),
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

    public function show(Listing $listing): View
    {
        $this->authorize('view', $listing);

        $endsOn = $this->now();

        return view('seller.listings.show', [
            'listing' => $listing->loadEventCounts()->load('activeRemoval'),
            'days' => ActivityTimeline::lastDays(
                $listing->eventCountsByDateSince(ActivityTimeline::firstDay($endsOn, self::ACTIVITY_WINDOW_DAYS)),
                $endsOn,
                self::ACTIVITY_WINDOW_DAYS,
            ),
            'windowDays' => self::ACTIVITY_WINDOW_DAYS,
            'sales' => $this->sales($listing),
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
}
