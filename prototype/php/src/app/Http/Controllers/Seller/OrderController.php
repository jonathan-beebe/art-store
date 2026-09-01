<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Orders\FulfillmentStatus;
use App\Models\Fulfillment;
use App\Models\Seller;
use App\Support\ListPaneWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\View\View;

/**
 * A seller's order is one fulfillment: the slice of a customer's order that
 * belongs to them. Index and show share one list pane (DSGN-006-style):
 * the show route's pane is the same default, unfiltered list the index
 * route opens with.
 */
final class OrderController extends SellerController
{
    public function index(): View
    {
        $seller = $this->seller();
        $window = ListPaneWindow::of($this->fulfillmentsQuery($seller));

        return view('seller.orders.index', [
            'fulfillments' => $window->items,
            'fulfillmentsTotal' => $window->total,
            'needsActionCount' => $this->needsActionCount($seller),
        ]);
    }

    public function show(Fulfillment $fulfillment): View
    {
        $this->authorize('view', $fulfillment);

        $seller = $this->seller();
        $window = ListPaneWindow::of($this->fulfillmentsQuery($seller), $fulfillment);

        return view('seller.orders.show', [
            'fulfillment' => $fulfillment->load([
                'order.items' => fn (Relation $items) => $items->where('seller_id', $seller->id),
                'order.latestPayment',
                'refund',
            ]),
            'cellFulfillments' => $window->items,
            'cellFulfillmentsTotal' => $window->total,
            'needsActionCount' => $this->needsActionCount($seller),
        ]);
    }

    private function needsActionCount(Seller $seller): int
    {
        return $seller->fulfillments()->where('status', FulfillmentStatus::AwaitingShipment)->count();
    }

    /**
     * @return Builder<Fulfillment>
     */
    private function fulfillmentsQuery(Seller $seller): Builder
    {
        return Fulfillment::query()
            ->whereBelongsTo($seller)
            ->with(['order.items' => fn (Relation $items) => $items->where('seller_id', $seller->id)])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
