<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Cart\AddToCart;
use App\Actions\Escrow\RunWeeklyPayout;
use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Domain\Orders\Purchaser;
use App\Domain\Orders\ShippingAddress;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Listing;
use App\Models\Order;
use DateTimeImmutable;
use Illuminate\Database\Seeder;

/**
 * Order history for two sellers, built through the same actions the seller
 * portal and storefront call: a paid order awaiting shipment, a shipped
 * order, and a delivered order whose escrow is released and paid out.
 */
class OrderHistorySeeder extends Seeder
{
    private const APPROVED_CARD = '4242424242424242';

    public function run(): void
    {
        $customer = Customer::where('email', CustomerSeeder::HERMIONE_EMAIL)->firstOrFail();
        $purchaser = Purchaser::onAccount(
            $customer->id,
            $customer->email,
            $customer->email_verified_at?->toDateTimeImmutable(),
        );

        $this->placeAndPay($customer, $purchaser, 'Burrow Kitchen Tea Bowl', new DateTimeImmutable('2026-07-06 09:00:00'));

        $shipped = $this->placeAndPay($customer, $purchaser, 'Gryffindor Common Room, Late Morning', new DateTimeImmutable('2026-07-07 09:00:00'));
        $this->ship($shipped, 'Owl Post', 'OWL-2263-1187-GB', new DateTimeImmutable('2026-07-08 09:00:00'));

        $delivered = $this->placeAndPay($customer, $purchaser, 'Garden Gnome in Reclaimed Oak', new DateTimeImmutable('2026-07-06 11:00:00'));
        $this->ship($delivered, 'Knight Bus Parcel', 'KB-9400-1189-2231', new DateTimeImmutable('2026-07-08 10:00:00'));
        $this->deliver($delivered, new DateTimeImmutable('2026-07-10 14:00:00'));

        app(RunWeeklyPayout::class)(new DateTimeImmutable('2026-07-16 09:00:00'));
    }

    private function placeAndPay(Customer $customer, Purchaser $purchaser, string $listingTitle, DateTimeImmutable $placedAt): Order
    {
        $cart = Cart::create(['customer_id' => $customer->id]);
        $listing = Listing::where('title', $listingTitle)->firstOrFail();

        app(AddToCart::class)($cart, $listing, 1, $placedAt);

        $order = app(PlaceOrder::class)($cart, $purchaser, $this->shippingAddress(), $placedAt);

        return app(FinalizeOrder::class)($order, self::APPROVED_CARD, $placedAt->modify('+5 minutes'));
    }

    private function ship(Order $order, string $carrier, string $trackingNumber, DateTimeImmutable $shippedAt): void
    {
        app(MarkShipped::class)($order->fulfillments()->firstOrFail(), $carrier, $trackingNumber, $shippedAt);
    }

    private function deliver(Order $order, DateTimeImmutable $deliveredAt): void
    {
        app(ConfirmDelivered::class)($order->fulfillments()->firstOrFail(), $deliveredAt);
    }

    private function shippingAddress(): ShippingAddress
    {
        return ShippingAddress::to(
            name: 'Hermione Granger',
            line1: '12 Heathgate',
            line2: null,
            city: 'London',
            region: 'Hampstead',
            postalCode: 'NW11 7EB',
            country: 'GB',
        );
    }
}
