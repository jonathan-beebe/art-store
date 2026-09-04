<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Auth\ActorType;
use App\Domain\Seller\CustomerRow;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\Seller;
use App\Support\ActorDisplay;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who a seller is talking to, beside the words: the counterpart's identity,
 * what they have bought from this seller, the piece or the parcel the
 * thread is about, and their other threads with this seller.
 *
 * A seller reads a buyer's numbers and their email because an order carried
 * them — a visitor who has only asked about a piece is a name and nothing
 * more, and a desk thread has no customer at all.
 */
final readonly class ThreadContext
{
    /**
     * @param  Collection<int, Conversation>  $others
     */
    private function __construct(
        public string $name,
        public ?string $email,
        public string $initials,
        public bool $isDesk,
        public ?CustomerRow $customer,
        public ?Listing $listing,
        public ?Fulfillment $order,
        public Collection $others,
    ) {}

    public static function forSeller(Seller $seller, Conversation $conversation): self
    {
        $conversation->loadMissing(['customer', 'listing', 'fulfillment.order.items']);

        $customer = $conversation->customer;
        $isDesk = $conversation->kind->isDesk();
        $row = $customer instanceof Customer && ! $isDesk
            ? SellerCustomers::forCustomer($seller, $customer)
            : null;
        $name = $conversation->counterpartName(ActorType::Seller);

        return new self(
            name: $name,
            email: $row?->email,
            initials: ActorDisplay::initialsFor($name),
            isDesk: $isDesk,
            customer: $row,
            listing: $conversation->listing,
            order: self::orderOf($seller, $conversation),
            others: self::otherThreads($seller, $conversation),
        );
    }

    /** The customer page, for a counterpart who has bought from this seller. */
    public function customerHref(): ?string
    {
        return $this->customer instanceof CustomerRow
            ? route('seller.customers.show', $this->customer->customerId)
            : null;
    }

    /**
     * The parcel the thread names, when it is this seller's own. An admin
     * raises a support thread over an order, and the seller reads the same
     * card there.
     */
    private static function orderOf(Seller $seller, Conversation $conversation): ?Fulfillment
    {
        $fulfillment = $conversation->fulfillment;

        return $fulfillment instanceof Fulfillment && $fulfillment->seller_id === $seller->id
            ? $fulfillment
            : null;
    }

    /**
     * Every other thread the seller holds with this buyer, newest first.
     *
     * @return Collection<int, Conversation>
     */
    private static function otherThreads(Seller $seller, Conversation $conversation): Collection
    {
        if ($conversation->customer_id === null) {
            /** @var Collection<int, Conversation> */
            return new Collection;
        }

        return $seller->conversations()
            ->where('customer_id', $conversation->customer_id)
            ->whereKeyNot($conversation->id)
            ->with(['listing', 'fulfillment'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }
}
