<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Listings\RemoveListing;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RemoveListingRequest;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;

final class ListingRemovalController extends Controller
{
    public function __invoke(RemoveListingRequest $request, Listing $listing, RemoveListing $removeListing): RedirectResponse
    {
        $removeListing($listing, $request->kind(), $request->reason());

        return redirect()->route('admin.listings.show', $listing)->with('status', 'Listing removed from the storefront.');
    }
}
