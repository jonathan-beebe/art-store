<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Reports\ListingStatusTally;
use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

final class SellerController extends Controller
{
    public function index(): View
    {
        return view('admin.sellers.index', [
            'sellers' => $this->sellers(),
            // One read of the whole ledger, folded per seller, rather than a
            // balance query for each row on the page.
            'balances' => LedgerEntry::balancesBySeller(),
        ]);
    }

    public function show(Seller $seller): View
    {
        return view('admin.sellers.show', [
            'seller' => $seller,
            'tally' => ListingStatusTally::from($seller->listingCountsByStatus()),
            'listings' => $seller->listings()->with('activeRemoval')->orderByDesc('created_at')->orderByDesc('id')->get(),
            'fulfillments' => $seller->fulfillments()->with('order')->orderByDesc('created_at')->orderByDesc('id')->get(),
            'payouts' => $seller->payouts()->orderByDesc('period_start')->get(),
            'balance' => $seller->escrowBalance(),
            // DSGN-006: the show route's list pane is the same list the
            // index route opens with.
            'cellSellers' => $this->sellers(),
            'cellBalances' => LedgerEntry::balancesBySeller(),
        ]);
    }

    /**
     * @return Collection<int, Seller>
     */
    private function sellers(): Collection
    {
        return Seller::query()->withCount(['listings', 'fulfillments'])->orderByDesc('created_at')->orderByDesc('id')->get();
    }
}
