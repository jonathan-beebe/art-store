<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class EarningsController extends Controller
{
    public function __invoke(): View
    {
        $seller = auth('seller')->user();

        return view('seller.earnings', [
            'fulfillments' => $seller->fulfillments()
                ->with(['order.items' => fn ($items) => $items->where('seller_id', $seller->id)])
                ->latest('id')
                ->get(),
            'balance' => $seller->escrowBalance(),
            'payouts' => $seller->payouts()->latest('period_start')->get(),
        ]);
    }
}
