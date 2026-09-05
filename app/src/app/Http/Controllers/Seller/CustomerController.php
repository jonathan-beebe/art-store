<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Seller\CustomerTally;
use App\Domain\Seller\EvergreenWindow;
use App\Http\Requests\Seller\CustomersQueryRequest;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\Seller;
use App\Seller\ActivityFeedReader;
use App\Seller\CustomerParcelRow;
use App\Seller\CustomersChrome;
use App\Seller\FeedFilters;
use App\Seller\FeedScope;
use App\Seller\ParcelLine;
use App\Seller\SellerCustomers;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\View\View;

/**
 * A seller's customer is a buyer: someone holding at least one paid parcel
 * with them that still stands. Every request derives the list. A customer
 * who has never bought here answers 404, which is the privacy rule: a
 * seller opens a person's page on the strength of an order.
 */
final class CustomerController extends SellerController
{
    public function index(CustomersQueryRequest $request): View
    {
        $seller = $this->seller();
        $newSince = AnalyticsRange::of(EvergreenWindow::DAYS, $this->now())->start;
        $segment = $request->customerSegment();
        $sort = $request->sort();
        $roundTripped = $request->roundTripped();

        $facts = SellerCustomers::tallyFor($seller, $newSince);
        $counts = SellerCustomers::conversationCounts($seller);

        $total = SellerCustomers::countForSegment($seller, $segment, $newSince);
        $page = $request->page($total);

        return view('seller.customers.index', [
            'rows' => SellerCustomers::pageForSeller($seller, $segment, $newSince, $request->sortColumn(), $request->sortDirection(), $page->limit, $page->offset),
            'tally' => CustomerTally::of($facts, $counts->open, $counts->unread),
            'chrome' => CustomersChrome::build($roundTripped, $segment, $sort),
            'rangeDays' => EvergreenWindow::DAYS,
            'page' => $page,
            'pagerQuery' => $roundTripped,
        ]);
    }

    public function show(Customer $customer, CustomersQueryRequest $request, ActivityFeedReader $reader): View
    {
        $seller = $this->seller();
        $row = SellerCustomers::forCustomer($seller, $customer);

        $kind = $request->kind();

        return view('seller.customers.show', [
            'customer' => $customer,
            'row' => $row,
            'feed' => $reader->read(FeedScope::forCustomer($seller, $customer))->filter($kind),
            'feedFilters' => FeedFilters::for('seller.customers.show', ['customer' => $customer->id], $kind),
            'fulfillments' => $this->fulfillmentsFor($seller, $customer),
            'favorites' => $this->favoritesFor($seller, $customer),
            'conversations' => $this->conversationsFor($seller, $customer),
            'now' => $this->now(),
        ]);
    }

    /**
     * Every parcel between the two of them, newest first — a declined or
     * refunded one included, which the numbers above leave out and the
     * seller still has to be able to look back at.
     *
     * @return list<CustomerParcelRow>
     */
    private function fulfillmentsFor(Seller $seller, Customer $customer): array
    {
        $fulfillments = $seller->fulfillments()
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

        return array_values($fulfillments->map(fn (Fulfillment $fulfillment): CustomerParcelRow => new CustomerParcelRow(
            href: route('seller.orders.show', $fulfillment->id),
            imageUrl: ParcelLine::imageUrl($fulfillment),
            itemLabel: ParcelLine::label($fulfillment),
            placed: $fulfillment->order->placed_at->format('M j, Y'),
            subtotal: $fulfillment->subtotal()->format(),
            statusLabel: $fulfillment->status->label(),
            tint: $fulfillment->status->badgeTint(),
        ))->all());
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
