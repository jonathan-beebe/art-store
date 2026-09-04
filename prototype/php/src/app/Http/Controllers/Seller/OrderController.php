<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Fulfillment\LaneFilter;
use App\Http\Requests\Seller\OrdersQueryRequest;
use App\Models\Fulfillment;
use App\Seller\ActivityFeedReader;
use App\Seller\CustomerOnOrder;
use App\Seller\FeedFilters;
use App\Seller\FeedScope;
use App\Seller\FulfillmentLanes;
use App\Seller\OrderDetail;
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
        OrderDetail $detail,
        ActivityFeedReader $feed,
        CustomerOnOrder $customers,
    ): View {
        $this->authorize('view', $fulfillment);

        $seller = $this->seller();
        $facts = $detail->facts($fulfillment, $seller, $this->now());
        $lane = $request->lane(LaneFilter::of($fulfillment->lane($facts->progress)));

        return view('seller.orders.show', [
            'fulfillment' => $fulfillment,
            'facts' => $facts,
            'customer' => $customers->facts($fulfillment),
            'canShip' => $seller->can('ship', $fulfillment),
            'canDecline' => $seller->can('decline', $fulfillment),
            'canCompleteStep' => $seller->can('completeStep', $fulfillment),
            'lane' => $lane,
            'tabs' => $lanes->tabs($seller, $lane),
            'pane' => $lanes->pane($seller, $lane, $fulfillment),
            'feed' => $feed->read(FeedScope::forFulfillment($fulfillment))->filter($request->kind()),
            'feedFilters' => FeedFilters::for(
                'seller.orders.show',
                ['fulfillment' => $fulfillment->id, 'lane' => $lane->value],
                $request->kind(),
            ),
        ]);
    }
}
