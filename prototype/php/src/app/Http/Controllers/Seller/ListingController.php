<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Listings\CreateListing;
use App\Actions\Listings\UpdateListing;
use App\Http\Requests\Seller\ListingRequest;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class ListingController extends SellerController
{
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

    public function edit(Listing $listing): View
    {
        $this->authorize('update', $listing);

        return view('seller.listings.edit', ['listing' => $listing]);
    }

    public function update(ListingRequest $request, Listing $listing, UpdateListing $updateListing): RedirectResponse
    {
        $this->authorize('update', $listing);

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
}
