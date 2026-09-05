<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Orders\FulfillmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Fulfillment;
use App\Models\Seller;
use App\Paging\ListPaneWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class FulfillmentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->enum('status', FulfillmentStatus::class);
        $sellerId = $request->filled('seller') ? $request->string('seller')->toString() : null;
        $window = ListPaneWindow::of($this->fulfillmentsQuery($status, $sellerId));

        return view('admin.fulfillments.index', [
            'fulfillments' => $window->items,
            'fulfillmentsTotal' => $window->total,
            'sellers' => Seller::query()->orderedForFilter()->get(),
            'status' => $status,
            'statuses' => FulfillmentStatus::cases(),
            'sellerId' => $sellerId,
        ]);
    }

    public function show(Fulfillment $fulfillment): View
    {
        // DSGN-006: the show route's list pane is the same default,
        // unfiltered list the index route opens with.
        $window = ListPaneWindow::of($this->fulfillmentsQuery(null, null), $fulfillment);

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
            'cellFulfillments' => $window->items,
            'cellFulfillmentsTotal' => $window->total,
        ]);
    }

    /**
     * @return Builder<Fulfillment>
     */
    private function fulfillmentsQuery(?FulfillmentStatus $status, ?string $sellerId): Builder
    {
        return Fulfillment::query()
            ->ofStatus($status)
            ->ofSeller($sellerId)
            ->with(['order.customer', 'seller'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
