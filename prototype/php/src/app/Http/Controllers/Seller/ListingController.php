<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Listings\CreateListing;
use App\Actions\Listings\UpdateListing;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ListingRequest;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class ListingController extends Controller
{
    public function index(): View
    {
        return view('seller.listings.index', [
            'listings' => auth('seller')->user()->listings()->withEventCounts()->latest('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('seller.listings.create');
    }

    public function store(ListingRequest $request, CreateListing $createListing): RedirectResponse
    {
        $listing = $createListing(auth('seller')->user(), $request->toDraft(), $request->file('image'));

        return redirect()
            ->route('seller.listings.index')
            ->with('status', "\"{$listing->title}\" is saved as a draft.".$this->imageUploadFailureNote($request, $listing));
    }

    public function edit(string $listing): View
    {
        return view('seller.listings.edit', ['listing' => $this->ownedListing($listing)]);
    }

    public function update(ListingRequest $request, string $listing, UpdateListing $updateListing): RedirectResponse
    {
        $updated = $updateListing($this->ownedListing($listing), $request->toDraft(), $request->file('image'));

        return redirect()
            ->route('seller.listings.index')
            ->with('status', "\"{$updated->title}\" is updated.");
    }

    private function ownedListing(string $id): Listing
    {
        return auth('seller')->user()->listings()->findOrFail($id);
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
