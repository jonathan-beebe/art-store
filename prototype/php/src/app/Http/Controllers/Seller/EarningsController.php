<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Escrow\LedgerEntryType;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\View\View;

final class EarningsController extends SellerController
{
    public function __invoke(): View
    {
        $seller = $this->seller();

        return view('seller.earnings', [
            'fulfillments' => $seller->fulfillments()
                ->with(['order.items' => fn (Relation $items) => $items->where('seller_id', $seller->id)])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
            'balance' => $seller->escrowBalance(),
            'refunds' => $seller->ledgerEntries()
                ->ofType(LedgerEntryType::Refunded)
                ->with('fulfillment')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->get(),
            'payouts' => $seller->payouts()->latest('period_start')->get(),
        ]);
    }
}
