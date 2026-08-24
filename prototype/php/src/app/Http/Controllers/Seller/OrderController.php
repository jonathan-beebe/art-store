<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Orders\FulfillmentStatus;
use App\Models\Fulfillment;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\View\View;

/**
 * A seller's order is one fulfillment: the slice of a customer's order that
 * belongs to them.
 */
final class OrderController extends SellerController
{
    public function index(): View
    {
        $seller = $this->seller();
        $byStatus = $this->fulfillments($seller)->get()->groupBy(fn (Fulfillment $fulfillment): string => $fulfillment->status->value);

        return view('seller.orders.index', [
            'groups' => array_map(fn (FulfillmentStatus $status): array => [
                'status' => $status,
                'label' => $status->label(),
                'fulfillments' => $byStatus->get($status->value, collect()),
            ], FulfillmentStatus::cases()),
        ]);
    }

    public function show(Fulfillment $fulfillment): View
    {
        $this->authorize('view', $fulfillment);

        $seller = $this->seller();

        return view('seller.orders.show', [
            'fulfillment' => $fulfillment->load([
                'order.items' => fn (Relation $items) => $items->where('seller_id', $seller->id),
            ]),
        ]);
    }

    /**
     * @return Builder<Fulfillment>
     */
    private function fulfillments(Seller $seller): Builder
    {
        return Fulfillment::query()
            ->whereBelongsTo($seller)
            ->with(['order.items' => fn (Relation $items) => $items->where('seller_id', $seller->id)])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
