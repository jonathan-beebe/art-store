<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Listings\CreateListing;
use App\Actions\Listings\UpdateListing;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Domain\Reports\ActivityTimeline;
use App\Http\Requests\Seller\ListingRequest;
use App\Models\Category;
use App\Models\Listing;
use App\Models\OrderItem;
use App\Support\Configurator\ListingBasicsPageData;
use App\Support\Configurator\ListingEditPageData;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class ListingController extends SellerController
{
    private const ACTIVITY_WINDOW_DAYS = 14;

    public function index(): View
    {
        return view('seller.listings.index', [
            'listings' => $this->seller()->listings()->withEventCounts()->orderByDesc('created_at')->orderByDesc('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('seller.listings.create', [
            'categories' => Category::orderBy('path')->get(),
        ]);
    }

    public function store(ListingRequest $request, CreateListing $createListing, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.create', [
                'categories' => Category::orderBy('path')->get(),
            ]);
        }

        $listing = $createListing($this->seller(), $request->toDraft(), $request->file('image'));

        return redirect()
            ->route('seller.listings.edit', $listing)
            ->with('status', "\"{$listing->title}\" is saved as a draft.".$this->imageUploadFailureNote($request, $listing));
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

        $updated = $updateListing($listing, $request->toDraft(), $request->file('image'));

        return redirect()
            ->route('seller.listings.basics.edit', $updated)
            ->with('status', "\"{$updated->title}\" is updated.");
    }

    /**
     * A new listing's file upload can fail the disk write after passing
     * validation; the listing is still saved, so this tells the seller the
     * image did not come along. Unset only signals a failed write here
     * because a create has no prior image to fall back to.
     */
    private function imageUploadFailureNote(ListingRequest $request, Listing $listing): string
    {
        return $request->hasFile('image') && ! $listing->images()->exists()
            ? ' The image failed to upload; try again from the listing.'
            : '';
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
