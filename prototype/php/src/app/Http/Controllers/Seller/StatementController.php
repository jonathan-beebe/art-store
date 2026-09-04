<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Seller\PeriodFigures;
use App\Seller\EarningsPeriods;
use App\Seller\PeriodSales;
use Illuminate\View\View;

/**
 * A printable statement for one payout period: the same figures the
 * earnings page's past-periods row shows, on their own page. Only a period
 * inside the eight the earnings page charts has a statement; any other
 * period answers 404.
 */
final class StatementController extends SellerController
{
    public function __invoke(string $period): View
    {
        $seller = $this->seller();
        $this->authorize('view', $seller);

        $now = $this->now();
        $periods = EarningsPeriods::for($seller, $now);

        $figures = $this->periodFigures($periods, $period);

        return view('seller.earnings.statement', [
            'seller' => $seller,
            'figures' => $figures,
            'settlement' => $periods->settlementOf($figures),
            'sales' => PeriodSales::for($seller, $figures->period),
            'generatedAt' => $now,
        ]);
    }

    private function periodFigures(EarningsPeriods $periods, string $period): PeriodFigures
    {
        foreach ($periods->periods as $figures) {
            if ($figures->period->start->format('Y-m-d') === $period) {
                return $figures;
            }
        }

        abort(404);
    }
}
