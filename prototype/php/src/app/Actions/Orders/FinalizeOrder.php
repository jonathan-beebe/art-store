<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Customers\CustomerStanding;
use App\Domain\Escrow\LedgerMovement;
use App\Domain\Orders\OrderPlacementRefused;
use App\Domain\Orders\OrderStatus;
use App\Domain\Payments\FakeCard;
use App\Domain\Payments\PaymentOutcome;
use App\Domain\Payments\PaymentStatus;
use App\Events\OrderPaid;
use App\Logging\StoryEvent;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\Variant;
use App\Support\Orders\OrderListingIds;
use App\Support\Orders\StockMovement;
use App\Support\Story;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

final readonly class FinalizeOrder
{
    public function __construct(private Analytics $analytics) {}

    public function __invoke(Order $order, string $cardNumber, DateTimeImmutable $now): Order
    {
        // The card number never leaves this method: the payment row keeps the
        // last four, and the story keeps neither.
        return Story::for(StoryEvent::OrderPay)->tell('taking payment for an order', [
            'order_id' => $order->id,
            'amount_cents' => $order->total_cents,
        ], function (Story $story) use ($order, $cardNumber, $now): Order {
            CustomerStanding::assertCanShop($order->loadMissing('customer')->customer->blockReason());

            $decision = FakeCard::decide($cardNumber);
            $outcome = PaymentOutcome::fromCardDecision($decision);
            $status = $order->status->transitionTo(OrderStatus::fromCardDecision($outcome));

            $retakesStock = $order->status->retakesStockOnRetry();

            $paid = DB::transaction(function () use ($order, $decision, $outcome, $retakesStock, $status, $now): Order {
                if ($retakesStock) {
                    // The decline that put this order here also put the
                    // stock it held back on the storefront, so a retry has
                    // to find the same listings still available before it
                    // claims them again — the same shape checkout refuses
                    // with, because the customer sitting on this page is
                    // exactly the shopper a stale cart would have refused.
                    // The re-check takes those rows for update, and the sale
                    // that follows writes the rows it read.
                    $this->assertStillAvailable($order);
                    $this->sellItems($order);
                }

                Payment::create([
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'status' => PaymentStatus::fromCardDecision($decision),
                    'amount_cents' => $order->total_cents,
                    'card_last_four' => $decision->lastFour,
                    'decline_reason' => $decision->declineReason,
                    'processed_at' => $now,
                ]);

                $order->update(['status' => $status]);

                match ($outcome) {
                    PaymentOutcome::Approved => $this->completePayment($order, $now),
                    PaymentOutcome::Declined => $this->restockItems($order),
                };

                return $order->refresh();
            });

            if ($outcome === PaymentOutcome::Approved) {
                $this->analytics->recordEvent(AnalyticsEvent::forOrder(
                    AnalyticsEventName::OrderPay,
                    $paid->id,
                    $paid->customer_id,
                    $now,
                    [
                        'listing_ids' => OrderListingIds::of($paid),
                        'total_cents' => $paid->total_cents,
                    ],
                ));
            }

            // A decline is the payment processor holding a rule, not the
            // application breaking, so it reads as a refusal.
            match ($outcome) {
                PaymentOutcome::Approved => $story->did('took the payment', [
                    'order_id' => $paid->id,
                    'amount_cents' => $paid->total_cents,
                    'status' => $paid->status->value,
                ]),
                PaymentOutcome::Declined => $story->refused('the card was declined', [
                    'order_id' => $paid->id,
                    'decline_reason' => $decision->declineReason?->value,
                    'status' => $paid->status->value,
                ]),
            };

            return $paid;
        });
    }

    private function completePayment(Order $order, DateTimeImmutable $now): void
    {
        $order->update(['finalized_at' => $now]);

        foreach ($order->fulfillments as $fulfillment) {
            $this->holdInEscrow($fulfillment, $now);
        }

        OrderPaid::dispatch($order, $now);
    }

    private function holdInEscrow(Fulfillment $fulfillment, DateTimeImmutable $now): void
    {
        $movement = LedgerMovement::hold($fulfillment->net());

        LedgerEntry::create([
            'seller_id' => $fulfillment->seller_id,
            'fulfillment_id' => $fulfillment->id,
            'type' => $movement->type,
            'amount_cents' => $movement->amount->cents,
            'occurred_at' => $now,
        ]);
    }

    private function assertStillAvailable(Order $order): void
    {
        $plan = $this->lockListings($order)->placementPlan();

        if (! $plan->isPlaceable()) {
            throw new OrderPlacementRefused($plan->blocked);
        }
    }

    /**
     * Takes the listing (and, for a configured line, variant/unit) rows
     * behind this order for update and reloads the items from them. Every
     * stock write in this transaction reads a quantity and writes the pair
     * back from what it read, so the rows are held from that read until the
     * commit and a concurrent checkout waits rather than overwriting the
     * result with its own stale arithmetic.
     */
    private function lockListings(Order $order): Order
    {
        return $order->load([
            'items.listing' => $this->takeForUpdate(...),
            'items.variant' => $this->takeForUpdateVariant(...),
            'items.unit' => $this->takeForUpdateUnit(...),
        ]);
    }

    /**
     * The eager load's constraint: the listing behind an order item is read
     * for update, so the row the re-check judges is the row the sale — or the
     * restock a decline follows with — writes back.
     *
     * @param  BelongsTo<Listing, OrderItem>  $listing
     */
    private function takeForUpdate(BelongsTo $listing): void
    {
        $listing->getQuery()->lockedForPlacement();
    }

    /**
     * @param  BelongsTo<Variant, OrderItem>  $variant
     */
    private function takeForUpdateVariant(BelongsTo $variant): void
    {
        $variant->getQuery()->lockedForPlacement();
    }

    /**
     * @param  BelongsTo<Unit, OrderItem>  $unit
     */
    private function takeForUpdateUnit(BelongsTo $unit): void
    {
        $unit->getQuery()->lockedForPlacement();
    }

    private function sellItems(Order $order): void
    {
        foreach ($order->items as $item) {
            StockMovement::claim($item);
        }
    }

    private function restockItems(Order $order): void
    {
        foreach ($this->lockListings($order)->items as $item) {
            StockMovement::release($item);
        }
    }
}
