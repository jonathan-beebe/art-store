<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Analytics\Admin\Funnel;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\FunnelDefinition;
use App\Domain\Reports\ListingStatusTally;
use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Seller;
use App\Support\ListPaneWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

final class SellerController extends Controller
{
    /** The seller page's funnel has no range control, so it always reads
     * the same window the rest of the admin analytics pages default to. */
    private const int FUNNEL_RANGE_DAYS = 30;

    public function index(): View
    {
        $window = ListPaneWindow::of($this->sellersQuery());

        return view('admin.sellers.index', [
            'sellers' => $window->items,
            'sellersTotal' => $window->total,
            // One read of the whole ledger, folded per seller, rather than a
            // balance query for each row on the page.
            'balances' => LedgerEntry::balancesBySeller(),
        ]);
    }

    public function show(Seller $seller): View
    {
        // DSGN-006: the show route's list pane is the same list the
        // index route opens with.
        $window = ListPaneWindow::of($this->sellersQuery(), $seller);

        return view('admin.sellers.show', [
            'seller' => $seller,
            'funnel' => Funnel::forSeller(FunnelDefinition::storefront(), $seller, AnalyticsRange::of(self::FUNNEL_RANGE_DAYS, $this->now())),
            'tally' => ListingStatusTally::from($seller->listingCountsByStatus()),
            'listings' => $seller->listings()->with('activeRemoval')->orderByDesc('created_at')->orderByDesc('id')->get(),
            'fulfillments' => $seller->fulfillments()->with('order')->orderByDesc('created_at')->orderByDesc('id')->get(),
            'payouts' => $seller->payouts()->orderByDesc('period_start')->get(),
            'balance' => $seller->escrowBalance(),
            'cellSellers' => $window->items,
            'cellSellersTotal' => $window->total,
            'cellBalances' => LedgerEntry::balancesBySeller(),
        ]);
    }

    /**
     * @return Builder<Seller>
     */
    private function sellersQuery(): Builder
    {
        return Seller::query()->withCount(['listings', 'fulfillments'])->orderByDesc('created_at')->orderByDesc('id');
    }
}
