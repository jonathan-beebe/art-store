<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\Customers\CustomerStanding;
use App\Domain\DomainRuleViolation;
use App\Domain\Escrow\LedgerMovement;
use App\Domain\Orders\OrderStatus;
use App\Domain\Payments\FakeCard;
use App\Domain\Payments\PaymentOutcome;
use App\Domain\Payments\PaymentStatus;
use App\Events\OrderPaid;
use App\Logging\StoryEvent;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Story;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class FinalizeOrder
{
    public function __invoke(Order $order, string $cardNumber, DateTimeImmutable $now): Order
    {
        // The card number never leaves this method: the payment row keeps the
        // last four, and the story keeps neither.
        $story = Story::for(StoryEvent::OrderPay)->will('taking payment for an order', [
            'order_id' => $order->id,
            'amount_cents' => $order->total_cents,
        ]);

        try {
            CustomerStanding::assertCanShop($order->loadMissing('customer')->customer->blockReason());

            $decision = FakeCard::decide($cardNumber);
            $outcome = PaymentOutcome::fromCardDecision($decision);
            $status = $order->status->transitionTo(OrderStatus::fromCardDecision($outcome));
        } catch (DomainRuleViolation $violation) {
            $story->refused($violation->getMessage(), ['order_id' => $order->id]);

            throw $violation;
        }

        $retakesStock = $order->status->retakesStockOnRetry();

        $paid = DB::transaction(function () use ($order, $decision, $outcome, $retakesStock, $status, $now): Order {
            if ($retakesStock) {
                $this->sellItems($order);
            }

            Payment::create([
                'order_id' => $order->id,
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

    private function sellItems(Order $order): void
    {
        foreach ($order->items as $item) {
            $item->listing->sell($item->quantity);
        }
    }

    private function restockItems(Order $order): void
    {
        foreach ($order->items as $item) {
            $item->listing->restock($item->quantity);
        }
    }
}
