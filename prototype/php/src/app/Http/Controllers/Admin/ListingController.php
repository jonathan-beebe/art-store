<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Listings\ListingStatus;
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

        return view('admin.listings.index', [
            'listings' => Listing::query()
                ->ofStatus($status)
                ->ofSeller($sellerId)
                ->with('seller')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
            'sellers' => Seller::query()->orderBy('shop_name')->orderBy('email')->get(),
            'status' => $status,
            'statuses' => ListingStatus::cases(),
            'sellerId' => $sellerId,
        ]);
    }

    public function show(Listing $listing): View
    {
        return view('admin.listings.show', [
            'listing' => $listing->load('seller')->loadEventCounts()->loadCount('favorites'),
            'sales' => OrderItem::query()
                ->where('listing_id', $listing->id)
                ->with('order')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
