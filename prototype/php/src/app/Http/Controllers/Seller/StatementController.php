<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Seller\StatementRequest;
use App\Seller\PeriodSales;
use Illuminate\View\View;
use RuntimeException;

/**
 * A printable statement for one payout period: the same figures the
 * earnings page's past-periods row shows, on their own page. Only a period
 * inside the eight the earnings page charts has a statement; any other
 * period answers 404.
 */
final class StatementController extends SellerController
{
    public function __invoke(StatementRequest $request): View
    {
        $seller = $this->seller();
        $figures = $request->figures()
            ?? throw new RuntimeException('authorize() already refused a request naming no period.');

        return view('seller.earnings.statement', [
            'seller' => $seller,
            'figures' => $figures,
            'settlement' => $request->periods()->settlementOf($figures),
            'sales' => PeriodSales::for($seller, $figures->period),
            'generatedAt' => $this->now(),
        ]);
    }
}
