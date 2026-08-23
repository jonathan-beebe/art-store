<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Notifications\Notify;
use App\Domain\Escrow\LedgerMovement;
use App\Domain\Notifications\NotificationMessage;
use App\Domain\Notifications\RecipientType;
use App\Domain\Orders\OrderStatus;
use App\Domain\Payments\FakeCard;
use App\Domain\Payments\PaymentOutcome;
use App\Domain\Payments\PaymentStatus;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class FinalizeOrder
{
    public function __construct(private Notify $notify) {}

    public function __invoke(Order $order, string $cardNumber, DateTimeImmutable $now): Order
    {
        $decision = FakeCard::decide($cardNumber);
        $outcome = PaymentOutcome::fromCardDecision($decision);
        $status = $order->status->transitionTo(OrderStatus::fromCardDecision($outcome));

        $retakesStock = $order->status->retakesStockOnRetry();

        return DB::transaction(function () use ($order, $decision, $outcome, $retakesStock, $status, $now): Order {
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
    }

    private function completePayment(Order $order, DateTimeImmutable $now): void
    {
        $order->update(['finalized_at' => $now]);

        foreach ($order->fulfillments as $fulfillment) {
            $this->holdInEscrow($fulfillment, $now);

            ($this->notify)(
                RecipientType::Seller,
                $fulfillment->seller_id,
                NotificationMessage::itemSold($order->id, $fulfillment->net()),
            );
        }
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
