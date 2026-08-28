<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\Cart\CartTotals;
use App\Domain\Configurator\PriceBreakdown;
use App\Domain\Configurator\PriceBreakdownLine;
use App\Domain\Customers\CustomerStanding;
use App\Domain\Escrow\Fee;
use App\Domain\Orders\OrderPlacementRefused;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\Purchaser;
use App\Domain\Orders\ShippingAddress;
use App\Logging\StoryEvent;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Unit;
use App\Models\Variant;
use App\Support\Orders\StockMovement;
use App\Support\Story;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class PlaceOrder
{
    public function __invoke(Cart $cart, Purchaser $purchaser, ShippingAddress $shipping, DateTimeImmutable $now): Order
    {
        // The address the order ships to is on the order, never in a line: an
        // actor's id is what a log line names them by.
        return Story::for(StoryEvent::OrderPlace)->tell('placing an order from the cart', [
            'cart_id' => $cart->id,
            'line_count' => $cart->items()->count(),
        ], function (Story $story) use ($cart, $purchaser, $shipping, $now): Order {
            CustomerStanding::assertCanShop($cart->loadMissing('customer')->customer->blockReason());

            $order = DB::transaction(function () use ($cart, $purchaser, $shipping, $now): Order {
                // Read fresh and for update, inside the transaction: the
                // listing (and, for a configured line, variant and unit) rows
                // the plan judges are the rows the stock claim writes back,
                // so holding them from this read to the commit is what stops
                // two shoppers both taking the last piece. A line that went
                // stale between the page and this submit is refused — every
                // blocked line at once — rather than half-placed.
                $cart->load([
                    'items.listing' => $this->takeForUpdate(...),
                    'items.variant' => $this->takeForUpdateVariant(...),
                    'items.unit' => $this->takeForUpdateUnit(...),
                ]);
                $plan = $cart->placementPlan();

                if (! $plan->isPlaceable()) {
                    throw new OrderPlacementRefused($plan->blocked);
                }

                $totals = CartTotals::forCheckout($cart->lines());

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
                    StockMovement::claim($item);
                }

                $cart->items()->delete();

                return $order;
            });

            $story->did('placed the order', [
                'order_id' => $order->id,
                'total_cents' => $order->total_cents,
                'status' => $order->status->value,
                'fulfillment_ids' => $order->fulfillments()->pluck('id')->all(),
            ]);

            return $order;
        });
    }

    /**
     * The eager load's constraint: the listing behind a cart line is read for
     * update, so the row the plan judges is the row the sale writes back.
     *
     * @param  BelongsTo<Listing, CartItem>  $listing
     */
    private function takeForUpdate(BelongsTo $listing): void
    {
        $listing->getQuery()->lockedForPlacement();
    }

    /**
     * @param  BelongsTo<Variant, CartItem>  $variant
     */
    private function takeForUpdateVariant(BelongsTo $variant): void
    {
        $variant->getQuery()->lockedForPlacement();
    }

    /**
     * @param  BelongsTo<Unit, CartItem>  $unit
     */
    private function takeForUpdateUnit(BelongsTo $unit): void
    {
        $unit->getQuery()->lockedForPlacement();
    }

    private function snapshotItems(Order $order, Cart $cart): void
    {
        foreach ($cart->items as $item) {
            $breakdown = $item->currentBreakdown();

            OrderItem::create([
                'order_id' => $order->id,
                'listing_id' => $item->listing_id,
                'seller_id' => $item->listing->seller_id,
                'title' => $item->listing->title,
                // A line's own per-unit price is only representative — the
                // total that matters is the frozen breakdown below, which
                // already folds in the quantity discount
                // `unit_price_cents * quantity` cannot express.
                'unit_price_cents' => intdiv($breakdown->total()->cents, $item->quantity),
                'quantity' => $item->quantity,
                'variant_id' => $item->variant_id,
                'unit_id' => $item->unit_id,
                'configuration_json' => $this->configurationSnapshot($item),
                'answers_json' => $item->answers_json,
                'price_breakdown_json' => $this->breakdownJson($breakdown),
            ]);
        }
    }

    /**
     * The buyer's axis choices, plus — for a serialized line — the specific
     * piece claimed, appended in the same shape so one render loop shows
     * both: never a live join back to the unit by id (`docs/item-configurator.md`'s
     * authorization note), just its label read once, here, while the row is
     * still in hand.
     *
     * @return list<array{axisId: string, axisName: string, optionValueId: string, optionValueLabel: string}>|null
     */
    private function configurationSnapshot(CartItem $item): ?array
    {
        if (! $item->isConfigured()) {
            return null;
        }

        $pairs = $item->configuration_json ?? [];

        if ($item->unit_id !== null) {
            $unit = $item->unit ?? throw new LogicException('A line naming a unit id always resolves to one.');

            $pairs[] = [
                'axisId' => 'unit',
                'axisName' => 'Piece',
                'optionValueId' => $item->unit_id,
                'optionValueLabel' => $unit->label,
            ];
        }

        return $pairs;
    }

    /**
     * @return list<array{label: string, cents: int}>
     */
    private function breakdownJson(PriceBreakdown $breakdown): array
    {
        return array_map(
            fn (PriceBreakdownLine $line): array => ['label' => $line->label, 'cents' => $line->amount->cents],
            $breakdown->lines,
        );
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
