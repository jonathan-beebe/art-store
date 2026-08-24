<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PayoutController extends Controller
{
    public function index(Request $request): View
    {
        $sellerId = $request->filled('seller') ? $request->string('seller')->toString() : null;

        return view('admin.payouts.index', [
            'payouts' => Payout::query()
                ->ofSeller($sellerId)
                ->with('seller')
                ->orderByDesc('period_start')
                ->orderByDesc('id')
                ->get(),
            'sellers' => Seller::query()->orderBy('shop_name')->orderBy('email')->get(),
            'sellerId' => $sellerId,
        ]);
    }
}
