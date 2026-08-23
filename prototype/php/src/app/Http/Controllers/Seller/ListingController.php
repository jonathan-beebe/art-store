<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Listings\CreateListing;
use App\Actions\Listings\UpdateListing;
use App\Domain\Reports\ActivityTimeline;
use App\Http\Requests\Seller\ListingRequest;
use App\Models\Listing;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class ListingController extends SellerController
{
    private const ACTIVITY_WINDOW_DAYS = 14;

    public function index(): View
    {
        return view('seller.listings.index', [
            'listings' => $this->seller()->listings()->withEventCounts()->latest('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('seller.listings.create');
    }

    public function store(ListingRequest $request, CreateListing $createListing): RedirectResponse
    {
        $listing = $createListing($this->seller(), $request->toDraft(), $request->file('image'));

        return redirect()
            ->route('seller.listings.index')
            ->with('status', "\"{$listing->title}\" is saved as a draft.".$this->imageUploadFailureNote($request, $listing));
    }

    public function show(Listing $listing): View
    {
        $this->authorize('view', $listing);

        $endsOn = $this->now();

        return view('seller.listings.show', [
            'listing' => $listing->loadEventCounts(),
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

        return view('seller.listings.edit', ['listing' => $listing]);
    }

    public function update(ListingRequest $request, Listing $listing, UpdateListing $updateListing): RedirectResponse
    {
        $updated = $updateListing($listing, $request->toDraft(), $request->file('image'));

        return redirect()
            ->route('seller.listings.index')
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
        return $request->hasFile('image') && $listing->image_path === null
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
            ->latest('id')
            ->get();
    }
}
