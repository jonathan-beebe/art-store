<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\PlatformFulfillmentReader;
use App\Domain\Analytics\PageViewDay;
use App\Domain\Analytics\PageViewWeek;
use App\Domain\Escrow\PlatformMoney;
use App\Domain\Reports\FulfillmentStatusTally;
use App\Domain\Reports\ListingStatusTally;
use App\Domain\Reports\OrderStatusTally;
use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Listing;
use App\Models\Order;
use App\Models\PageViewCount;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $balances = LedgerEntry::balancesBySeller();

        return view('admin.dashboard', [
            'listings' => ListingStatusTally::from(Listing::platformCountsByStatus()),
            'orders' => OrderStatusTally::from(Order::platformCountsByStatus()),
            'fulfillments' => FulfillmentStatusTally::from(PlatformFulfillmentReader::countsByStatus()),
            'money' => PlatformMoney::of($balances->total(), PlatformFulfillmentReader::fees()),
            'pageViewsThisWeek' => PageViewCount::totalForWeek(PageViewWeek::endingOn(PageViewDay::of($this->now()))),
        ]);
    }
}
