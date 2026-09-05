<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Configurator\ListingBasicsPageData;
use App\Models\Listing;
use Illuminate\View\View;

final class ListingBasicsController extends SellerController
{
    public function edit(Listing $listing): View
    {
        $this->authorize('update', $listing);

        return view('seller.listings.basics.edit', ListingBasicsPageData::for($listing));
    }
}
