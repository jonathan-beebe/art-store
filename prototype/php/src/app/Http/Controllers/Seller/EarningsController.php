<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

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
                ->latest('id')
                ->get(),
            'balance' => $seller->escrowBalance(),
            'payouts' => $seller->payouts()->latest('period_start')->get(),
        ]);
    }
}
