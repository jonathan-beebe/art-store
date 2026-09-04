<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Analytics\AnalyticsRange;
use App\Http\Requests\Seller\DashboardQueryRequest;
use App\Seller\ListingActivity;
use App\Seller\NavLinks;
use App\Seller\NeedsAttention;
use App\Seller\NextPayout;
use App\Seller\SellerOverview;
use Illuminate\View\View;

/**
 * The morning briefing: three numbers with direction, the listings buyers
 * are looking at, and what wants doing today. The next payout is read once
 * and handed to both the tiles and the focus row, so the page names one
 * payout date.
 */
final class DashboardController extends SellerController
{
    public function __invoke(DashboardQueryRequest $request): View
    {
        $seller = $this->seller();
        $now = $this->now();
        $range = AnalyticsRange::of($request->rangeDays(), $now);
        $payout = NextPayout::for($seller, $now)->estimate;

        return view('seller.dashboard', [
            'storeName' => $seller->displayName(),
            'range' => $range,
            'rangeLinks' => NavLinks::for(
                routeName: 'seller.dashboard',
                without: [],
                param: 'range',
                cases: AnalyticsRange::SIZES,
                label: fn (int $days): string => $days.' days',
                value: fn (int $days): string => (string) $days,
                active: fn (int $days): bool => $days === $range->days,
            ),
            'tiles' => SellerOverview::for($seller, $range, $payout)->tiles(),
            'activity' => ListingActivity::for($seller, $range),
            'attention' => NeedsAttention::for($seller, $payout, $now),
        ]);
    }
}
