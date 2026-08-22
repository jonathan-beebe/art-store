<?php

namespace App\Http\Controllers\Seller;

use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Reports\StatusLabel;
use App\Http\Controllers\Controller;
use App\Models\Fulfillment;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/**
 * A seller's order is one fulfillment: the slice of a customer's order that
 * belongs to them.
 */
final class OrderController extends Controller
{
    public function index(): View
    {
        $seller = auth('seller')->user();
        $byStatus = $this->fulfillments($seller)->get()->groupBy(fn (Fulfillment $fulfillment): string => $fulfillment->status->value);

        return view('seller.orders.index', [
            'groups' => array_map(fn (FulfillmentStatus $status): array => [
                'status' => $status,
                'label' => StatusLabel::of($status),
                'fulfillments' => $byStatus->get($status->value, collect()),
            ], FulfillmentStatus::cases()),
        ]);
    }

    public function show(string $fulfillment): View
    {
        $seller = auth('seller')->user();

        return view('seller.orders.show', [
            'fulfillment' => $this->fulfillments($seller)->findOrFail($fulfillment),
        ]);
    }

    /**
     * @return Builder<Fulfillment>
     */
    private function fulfillments(Seller $seller): Builder
    {
        return $seller->fulfillments()
            ->getQuery()
            ->with(['order.items' => fn ($items) => $items->where('seller_id', $seller->id)])
            ->latest('id');
    }
}
