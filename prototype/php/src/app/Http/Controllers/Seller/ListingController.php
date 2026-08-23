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
            ->with('status', "\"{$listing->title}\" is saved as a draft.");
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
}
