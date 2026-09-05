<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Listings\ChangeListingStatus;
use App\Domain\Configurator\ConfiguratorPublishRefused;
use App\Http\Requests\Seller\ChangeListingStatusRequest;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;

final class ListingStatusController extends SellerController
{
    public function __invoke(ChangeListingStatusRequest $request, Listing $listing, ChangeListingStatus $changeStatus): RedirectResponse
    {
        $next = $request->status();

        try {
            $changeStatus($listing, $next);
        } catch (ConfiguratorPublishRefused) {
            // The edit screen lists every issue and links each to the screen
            // that owns it, so the refusal lands there rather than back on
            // the list the form was submitted from.
            return redirect()->route('seller.listings.edit', $listing);
        }

        return redirect()
            ->route('seller.listings.index')
            ->with('status', "\"{$listing->title}\" is now ".lcfirst($next->label()).'.');
    }
}
