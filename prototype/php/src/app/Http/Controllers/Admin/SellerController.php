<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Reports\ListingStatusTally;
use App\Models\Seller;
use Illuminate\View\View;

final class SellerController extends AdminController
{
    public function index(): View
    {
        return view('admin.sellers.index', [
            'sellers' => Seller::query()->withCount(['listings', 'fulfillments'])->latest('id')->get(),
        ]);
    }

    public function show(Seller $seller): View
    {
        return view('admin.sellers.show', [
            'seller' => $seller,
            'tally' => ListingStatusTally::from($seller->listingCountsByStatus()),
            'fulfillmentCount' => $seller->fulfillments()->count(),
        ]);
    }
}
