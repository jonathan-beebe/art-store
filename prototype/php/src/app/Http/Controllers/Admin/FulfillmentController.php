<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Orders\FulfillmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Fulfillment;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class FulfillmentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->enum('status', FulfillmentStatus::class);
        $sellerId = $request->filled('seller') ? $request->string('seller')->toString() : null;

        return view('admin.fulfillments.index', [
            'fulfillments' => Fulfillment::query()
                ->ofStatus($status)
                ->ofSeller($sellerId)
                ->with(['order', 'seller'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
            'sellers' => Seller::query()->orderBy('shop_name')->orderBy('email')->get(),
            'status' => $status,
            'statuses' => FulfillmentStatus::cases(),
            'sellerId' => $sellerId,
        ]);
    }

    public function show(Fulfillment $fulfillment): View
    {
        return view('admin.fulfillments.show', [
            'fulfillment' => $fulfillment->load([
                'seller',
                'order.customer',
                // The lines this fulfillment ships: the order's items from
                // this seller, which is how the order was split in the first
                // place.
                'order.items' => fn (Relation $items) => $items->where('seller_id', $fulfillment->seller_id),
                'ledgerEntries',
                'refund',
            ]),
        ]);
    }
}
