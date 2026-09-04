<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Fulfillment\DefaultFlow;
use App\Domain\Fulfillment\LaneFilter;
use App\Http\Requests\Seller\OrdersQueryRequest;
use App\Models\Fulfillment;
use App\Models\FulfillmentFlow;
use App\Seller\ActivityFeedReader;
use App\Seller\CustomerOnOrder;
use App\Seller\FeedFilter;
use App\Seller\FeedScope;
use App\Seller\FulfillmentLanes;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\View\View;

/**
 * A seller's order is one fulfillment: the slice of a customer's order that
 * belongs to them. Index and show share one list pane (DSGN-006-style), and
 * the lane is a query parameter both routes read: the index opens on the
 * pile that asks for work, and a detail reached by a link that named no lane
 * opens on the pile its own parcel sits in, so the row is always in the pane
 * beside it.
 */
final class OrderController extends SellerController
{
    public function index(OrdersQueryRequest $request, FulfillmentLanes $lanes): View
    {
        $seller = $this->seller();
        $lane = $request->lane(LaneFilter::default());

        return view('seller.orders.index', [
            'lane' => $lane,
            'tabs' => $lanes->tabs($seller, $lane),
            'pane' => $lanes->pane($seller, $lane),
        ]);
    }

    public function show(
        OrdersQueryRequest $request,
        Fulfillment $fulfillment,
        FulfillmentLanes $lanes,
        ActivityFeedReader $feed,
        CustomerOnOrder $customers,
    ): View {
        $this->authorize('view', $fulfillment);

        $seller = $this->seller();

        $fulfillment->load([
            'customer',
            'order.items' => fn (Relation $items) => $items->where('seller_id', $seller->id),
            'order.items.listing.fulfillmentFlow',
            'order.latestPayment',
            'ledgerEntries',
            'refund',
            'fulfillmentEvents',
            'seller.defaultFulfillmentFlow',
        ]);

        $lane = $request->lane(LaneFilter::of($fulfillment->lane()));
        $flow = $fulfillment->flowInEffect();

        return view('seller.orders.show', [
            'fulfillment' => $fulfillment,
            'stateLine' => $fulfillment->state($this->now())->line(),
            'customer' => $customers->facts($fulfillment),
            'escrow' => $fulfillment->escrowState(),
            'flowName' => $flow instanceof FulfillmentFlow ? $flow->name : DefaultFlow::NAME,
            'flowSteps' => $fulfillment->flowSteps(),
            'progress' => $fulfillment->progress(),
            'lane' => $lane,
            'tabs' => $lanes->tabs($seller, $lane),
            'pane' => $lanes->pane($seller, $lane, $fulfillment),
            'feed' => $feed->read(FeedScope::forFulfillment($fulfillment))->filter($request->kind()),
            'feedFilter' => FeedFilter::build(
                'seller.orders.show',
                ['fulfillment' => $fulfillment->id, 'lane' => $lane->value],
                $request->kind(),
            ),
        ]);
    }
}
