<?php

namespace App\Actions\Orders;

use App\Actions\Notifications\Notify;
use App\Domain\Escrow\LedgerMovement;
use App\Domain\Listings\ListingStock;
use App\Domain\Notifications\NotificationMessage;
use App\Domain\Notifications\RecipientType;
use App\Domain\Orders\OrderStatus;
use App\Domain\Payments\FakeCard;
use App\Domain\Payments\PaymentStatus;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class FinalizeOrder
{
    public function __construct(private readonly Notify $notify) {}

    public function __invoke(Order $order, string $cardNumber, DateTimeImmutable $now): Order
    {
        $decision = FakeCard::decide($cardNumber);
        $status = $order->status->transitionTo(OrderStatus::fromCardDecision($decision));

        return DB::transaction(function () use ($order, $decision, $status, $now): Order {
            // A declined charge put the stock back on the storefront, so a retry
            // has to claim it again before the order can be paid.
            match ($order->status) {
                OrderStatus::PaymentFailed => $this->takeStock($order),
                default => null,
            };

            Payment::create([
                'order_id' => $order->id,
                'status' => PaymentStatus::fromCardDecision($decision),
                'amount_cents' => $order->total_cents,
                'card_last_four' => $decision->lastFour,
                'decline_reason' => $decision->declineReason,
                'processed_at' => $now,
            ]);

            $order->update(['status' => $status]);

            match ($status) {
                OrderStatus::Paid => $this->completePayment($order, $now),
                OrderStatus::PaymentFailed => $this->releaseStock($order),
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

    private function takeStock(Order $order): void
    {
        foreach ($order->items as $item) {
            $listing = $item->listing;
            $stock = ListingStock::afterSale($listing->quantity, $listing->status, $item->quantity);
            $listing->update(['quantity' => $stock->quantity, 'status' => $stock->status]);
        }
    }

    private function releaseStock(Order $order): void
    {
        foreach ($order->items as $item) {
            $listing = $item->listing;
            $stock = ListingStock::afterRestock($listing->quantity, $listing->status, $item->quantity);
            $listing->update(['quantity' => $stock->quantity, 'status' => $stock->status]);
        }
    }
}
