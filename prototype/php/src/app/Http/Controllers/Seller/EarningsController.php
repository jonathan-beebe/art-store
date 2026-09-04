<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Seller\EarningsPeriods;
use App\Seller\HeldEscrow;
use App\Seller\NextPayout;
use App\Seller\PeriodSales;
use Illuminate\Support\Facades\Date;
use Illuminate\View\View;

/**
 * The earnings page: the next payout, what is still held and why, this
 * period against the seven before it, and a bar per period.
 */
final class EarningsController extends SellerController
{
    public function __invoke(): View
    {
        $seller = $this->seller();
        $now = Date::now()->toDateTimeImmutable();
        $periods = EarningsPeriods::for($seller, $now);

        return view('seller.earnings', [
            'nextPayout' => NextPayout::for($seller, $now),
            'held' => HeldEscrow::for($seller),
            'periods' => $periods,
            'currentSales' => PeriodSales::for($seller, $periods->current()->period),
        ]);
    }
}
