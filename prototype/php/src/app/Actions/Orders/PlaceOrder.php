<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\Cart\CartTotals;
use App\Domain\Escrow\Fee;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\Purchaser;
use App\Domain\Orders\ShippingAddress;
use App\Models\Cart;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class PlaceOrder
{
    public function __invoke(Cart $cart, Purchaser $purchaser, ShippingAddress $shipping, DateTimeImmutable $now): Order
    {
        $cart->load('items.listing');
        $totals = CartTotals::forCheckout($cart->lines());

        return DB::transaction(function () use ($cart, $purchaser, $shipping, $totals, $now): Order {
            $order = Order::create($shipping->attributes() + [
                'customer_id' => $purchaser->customerId,
                'email' => $purchaser->email,
                'status' => OrderStatus::forPlacement($purchaser),
                'subtotal_cents' => $totals->subtotal->cents,
                'total_cents' => $totals->subtotal->cents,
                'placed_at' => $now,
            ]);

            $this->snapshotItems($order, $cart);
            $this->splitBySeller($order, $totals);

            foreach ($cart->items as $item) {
                $item->listing->sell($item->quantity);
            }

            $cart->items()->delete();

            return $order;
        });
    }

    private function snapshotItems(Order $order, Cart $cart): void
    {
        foreach ($cart->items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'listing_id' => $item->listing_id,
                'seller_id' => $item->listing->seller_id,
                'title' => $item->listing->title,
                'unit_price_cents' => $item->listing->price_cents,
                'quantity' => $item->quantity,
            ]);
        }
    }

    private function splitBySeller(Order $order, CartTotals $totals): void
    {
        foreach ($totals->subtotalsBySeller() as $sellerId => $subtotal) {
            Fulfillment::create([
                'order_id' => $order->id,
                'seller_id' => $sellerId,
                'subtotal_cents' => $subtotal->cents,
                'fee_cents' => Fee::platform($subtotal)->cents,
                'net_cents' => Fee::net($subtotal)->cents,
            ]);
        }
    }
}
