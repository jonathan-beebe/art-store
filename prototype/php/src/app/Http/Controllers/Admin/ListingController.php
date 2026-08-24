<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Listings\ListingStatus;
use App\Domain\Listings\RemovedFilter;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\OrderItem;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ListingController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->enum('status', ListingStatus::class);
        $sellerId = $request->filled('seller') ? $request->string('seller')->toString() : null;
        $removed = $request->enum('removed', RemovedFilter::class) ?? RemovedFilter::Any;

        return view('admin.listings.index', [
            'listings' => Listing::query()
                ->ofStatus($status)
                ->ofSeller($sellerId)
                ->ofRemoval($removed)
                ->with(['seller', 'activeRemoval'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
            'sellers' => Seller::query()->orderBy('shop_name')->orderBy('email')->get(),
            'status' => $status,
            'statuses' => ListingStatus::cases(),
            'sellerId' => $sellerId,
            'removed' => $removed,
            'removedFilters' => RemovedFilter::cases(),
        ]);
    }

    public function show(Listing $listing): View
    {
        return view('admin.listings.show', [
            'listing' => $listing->load(['seller', 'activeRemoval'])->loadEventCounts()->loadCount('favorites'),
            'removals' => $listing->removals()->orderByDesc('created_at')->orderByDesc('id')->get(),
            'sales' => OrderItem::query()
                ->where('listing_id', $listing->id)
                ->with('order')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
