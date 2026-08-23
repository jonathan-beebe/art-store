<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Reports\StatusLabel;
use App\Http\Requests\Seller\ChangeListingStatusRequest;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;

final class ListingStatusController extends SellerController
{
    public function __invoke(ChangeListingStatusRequest $request, Listing $listing): RedirectResponse
    {
        $next = $request->status();
        $listing->changeStatusTo($next);

        return redirect()
            ->route('seller.listings.index')
            ->with('status', "\"{$listing->title}\" is now ".lcfirst(StatusLabel::of($next)).'.');
    }
}
