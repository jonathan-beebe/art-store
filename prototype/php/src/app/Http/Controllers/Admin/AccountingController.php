<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Escrow\PlatformMoney;
use App\Http\Controllers\Controller;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Models\Seller;
use Illuminate\View\View;

final class AccountingController extends Controller
{
    public function __invoke(): View
    {
        // One read of the whole ledger, folded per seller and folded again
        // into the platform's own total — no query per seller, no second
        // read for the total.
        $balances = LedgerEntry::balancesBySeller();

        return view('admin.accounting.index', [
            'sellers' => Seller::query()->orderedForFilter()->get(),
            'balances' => $balances,
            'totals' => PlatformMoney::of($balances->total(), Fulfillment::platformFees()),
        ]);
    }
}
