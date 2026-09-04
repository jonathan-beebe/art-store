<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Seller\CustomerRow;
use App\Domain\Seller\CustomerTableSort;
use App\Domain\Seller\CustomerTally;
use App\Http\Requests\Seller\CustomersQueryRequest;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\Seller;
use App\Seller\ActivityFeedReader;
use App\Seller\CustomersChrome;
use App\Seller\FeedFilters;
use App\Seller\FeedScope;
use App\Seller\SellerCustomers;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\View\View;

/**
 * A seller's customer is a buyer: someone holding at least one live
 * fulfillment with them. The list is derived on every request rather than
 * stored, and a customer who has never bought here answers 404 — browsing,
 * favoriting, and asking about a piece do not open a person's page to a
 * seller.
 */
final class CustomerController extends SellerController
{
    public function index(CustomersQueryRequest $request): View
    {
        $seller = $this->seller();
        $range = AnalyticsRange::of($request->rangeDays(), $this->now());
        $segment = $request->customerSegment();
        $sort = $request->sort();

        $rows = SellerCustomers::forSeller($seller);
        $counts = SellerCustomers::conversationCounts($seller);

        return view('seller.customers.index', [
            'rows' => CustomerTableSort::apply($sort, $segment->apply($rows, $range->start)),
            'tally' => CustomerTally::of($rows, $range->start, $counts->open, $counts->unread),
            'chrome' => CustomersChrome::build($request->roundTripped(), $segment, $sort),
            'rangeDays' => $range->days,
        ]);
    }

    public function show(Customer $customer, CustomersQueryRequest $request, ActivityFeedReader $reader): View
    {
        $seller = $this->seller();
        $row = SellerCustomers::forCustomer($seller, $customer);

        abort_if(! $row instanceof CustomerRow, 404);

        $kind = $request->kind();

        return view('seller.customers.show', [
            'customer' => $customer,
            'row' => $row,
            'feed' => $reader->read(FeedScope::forCustomer($seller, $customer))->filter($kind),
            'feedFilters' => FeedFilters::for('seller.customers.show', ['customer' => $customer->id], $kind),
            'fulfillments' => $this->fulfillmentsFor($seller, $customer),
            'favorites' => $this->favoritesFor($seller, $customer),
            'conversations' => $this->conversationsFor($seller, $customer),
        ]);
    }

    /**
     * Every parcel between the two of them, newest first — a declined or
     * refunded one included, which the numbers above leave out and the
     * seller still has to be able to look back at.
     *
     * @return Collection<int, Fulfillment>
     */
    private function fulfillmentsFor(Seller $seller, Customer $customer): Collection
    {
        return $seller->fulfillments()
            ->where('fulfillments.customer_id', $customer->id)
            ->with([
                'order.items' => fn (Relation $items) => $items->where('seller_id', $seller->id),
                'order.items.listing.images' => fn (Relation $images) => $images->orderBy('position'),
            ])
            ->join('orders', 'orders.id', '=', 'fulfillments.order_id')
            ->orderByDesc('orders.placed_at')
            ->orderByDesc('fulfillments.id')
            ->select('fulfillments.*')
            ->get();
    }

    /**
     * The seller's own listings this buyer holds as a favorite.
     *
     * @return Collection<int, Listing>
     */
    private function favoritesFor(Seller $seller, Customer $customer): Collection
    {
        return $customer->favoriteListings()
            ->where('listings.seller_id', $seller->id)
            ->with(['images' => fn (Relation $images) => $images->orderBy('position')])
            ->orderByDesc('favorites.created_at')
            ->get();
    }

    /**
     * Every thread between the two of them, the inbox's own order.
     *
     * @return Collection<int, Conversation>
     */
    private function conversationsFor(Seller $seller, Customer $customer): Collection
    {
        return $seller->conversations()
            ->where('customer_id', $customer->id)
            ->with(['listing', 'fulfillment'])
            ->orderByDesc('last_message_at')
            ->get();
    }
}
