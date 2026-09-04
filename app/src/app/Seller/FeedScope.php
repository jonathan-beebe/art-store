<?php

declare(strict_types=1);

namespace App\Seller;

use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Seller;
use App\Support\ActorDisplay;

/**
 * Which story a feed tells: everything between one seller and one buyer, or
 * everything on one order. Both scopes carry the same shape, so a source
 * never asks which one it is answering — beyond narrowing threads, which
 * only an order scope does.
 */
final readonly class FeedScope
{
    /**
     * @param  list<string>  $fulfillmentIds
     * @param  list<string>  $listingIds
     */
    private function __construct(
        public string $sellerId,
        public string $customerId,
        public string $customerName,
        public array $fulfillmentIds,
        public array $listingIds,
        public bool $isOneOrder,
    ) {}

    /**
     * One parcel: its own listings, and the threads about it or about them.
     */
    public static function forFulfillment(Fulfillment $fulfillment): self
    {
        $fulfillment->loadMissing(['customer', 'order.items']);

        $listingIds = $fulfillment->order->items
            ->where('seller_id', $fulfillment->seller_id)
            ->map(fn ($item): string => (string) $item->listing_id)
            ->unique()
            ->values()
            ->all();

        return new self(
            sellerId: $fulfillment->seller_id,
            customerId: $fulfillment->customer_id,
            customerName: ActorDisplay::nameOf($fulfillment->customer),
            fulfillmentIds: [$fulfillment->id],
            listingIds: array_values($listingIds),
            isOneOrder: true,
        );
    }

    /**
     * Every parcel this buyer has had from this seller, and every listing the
     * seller has for them to have touched.
     */
    public static function forCustomer(Seller $seller, Customer $customer): self
    {
        return new self(
            sellerId: $seller->id,
            customerId: $customer->id,
            customerName: ActorDisplay::nameOf($customer),
            fulfillmentIds: self::ids($seller->fulfillments()->where('customer_id', $customer->id)->pluck('id')->all()),
            listingIds: self::ids($seller->listings()->pluck('id')->all()),
            isOneOrder: false,
        );
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private static function ids(array $values): array
    {
        return array_values(array_filter($values, is_string(...)));
    }
}
