<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Listings\ListingStatus;
use App\Domain\Listings\RemovedFilter;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Support\ListPaneWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ListingController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->enum('status', ListingStatus::class);
        $sellerId = $request->filled('seller') ? $request->string('seller')->toString() : null;
        $removed = $request->enum('removed', RemovedFilter::class) ?? RemovedFilter::Any;
        $window = ListPaneWindow::of($this->listingsQuery($status, $sellerId, $removed));

        return view('admin.listings.index', [
            'listings' => $window->items,
            'listingsTotal' => $window->total,
            'sellers' => Seller::query()->orderedForFilter()->get(),
            'status' => $status,
            'statuses' => ListingStatus::cases(),
            'sellerId' => $sellerId,
            'removed' => $removed,
            'removedFilters' => RemovedFilter::cases(),
        ]);
    }

    public function show(Listing $listing): View
    {
        // DSGN-006: the show route's list pane is the same default,
        // unfiltered list the index route opens with.
        $window = ListPaneWindow::of($this->listingsQuery(null, null, RemovedFilter::Any), $listing);

        return view('admin.listings.show', [
            'listing' => $listing->load(['seller', 'activeRemoval'])->loadEventCounts()->loadCount('favorites'),
            'removals' => $listing->removals()->orderByDesc('created_at')->orderByDesc('id')->get(),
            'sales' => OrderItem::query()
                ->where('listing_id', $listing->id)
                ->with('order')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
            'cellListings' => $window->items,
            'cellListingsTotal' => $window->total,
        ]);
    }

    /**
     * @return Builder<Listing>
     */
    private function listingsQuery(?ListingStatus $status, ?string $sellerId, RemovedFilter $removed): Builder
    {
        return Listing::query()
            ->ofStatus($status)
            ->ofSeller($sellerId)
            ->ofRemoval($removed)
            ->with(['seller', 'activeRemoval'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
