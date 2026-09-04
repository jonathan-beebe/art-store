<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Money\Money;
use App\Domain\Payments\PaymentStatus;
use App\Domain\Seller\ActivityKind;
use App\Domain\Seller\FeedEvent;
use App\Domain\Seller\FeedIcon;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Support\ParcelLine;

/**
 * The money half of the feed: the order the buyer placed, every card attempt
 * on it, and the escrow movements this parcel produced.
 */
final readonly class OrderSource implements ActivityFeedSource
{
    /**
     * @return list<FeedEvent>
     */
    public function events(FeedScope $scope): array
    {
        $events = [];

        foreach ($this->fulfillments($scope) as $fulfillment) {
            $events = array_merge(
                $events,
                $this->placementOf($fulfillment, $scope),
                $this->paymentsOf($fulfillment),
                $this->movementsOf($fulfillment),
            );
        }

        return $events;
    }

    /**
     * @return list<Fulfillment>
     */
    private function fulfillments(FeedScope $scope): array
    {
        if ($scope->fulfillmentIds === []) {
            return [];
        }

        return array_values(Fulfillment::query()
            ->whereIn('id', $scope->fulfillmentIds)
            ->with(['order.items', 'order.payments', 'ledgerEntries', 'refund'])
            ->get()
            ->all());
    }

    /**
     * @return list<FeedEvent>
     */
    private function placementOf(Fulfillment $fulfillment, FeedScope $scope): array
    {
        $order = $fulfillment->order;
        $placedAt = $order->placed_at->toDateTimeImmutable();
        $label = ParcelLine::label($fulfillment);

        return [new FeedEvent(
            occurredAt: $placedAt,
            kind: ActivityKind::Order,
            icon: FeedIcon::Bag,
            actor: $scope->customerName,
            text: "placed order {$order->id} · {$label} · ".$fulfillment->subtotal()->format(),
            link: route('seller.orders.show', $fulfillment->id),
        )];
    }

    /**
     * @return list<FeedEvent>
     */
    private function paymentsOf(Fulfillment $fulfillment): array
    {
        $events = [];

        foreach ($fulfillment->order->payments as $payment) {
            $events[] = new FeedEvent(
                occurredAt: $payment->processed_at->toDateTimeImmutable(),
                kind: ActivityKind::Order,
                icon: FeedIcon::Card,
                actor: 'Payment',
                text: $this->paymentText($payment),
            );
        }

        return $events;
    }

    private function paymentText(Payment $payment): string
    {
        $card = "on card ending {$payment->card_last_four}";

        return $payment->status === PaymentStatus::Approved
            ? "approved {$card} · ".$payment->amount()->format()
            : "declined {$card} — ".($payment->decline_reason?->message() ?? 'the card was refused');
    }

    /**
     * @return list<FeedEvent>
     */
    private function movementsOf(Fulfillment $fulfillment): array
    {
        $events = [];

        foreach ($fulfillment->ledgerEntries as $entry) {
            $events[] = new FeedEvent(
                occurredAt: $entry->occurred_at->toDateTimeImmutable(),
                kind: ActivityKind::Order,
                icon: FeedIcon::Cash,
                actor: $this->movementAmount($fulfillment, $entry)->format(),
                text: $this->movementText($fulfillment, $entry->type),
                quote: $entry->type === LedgerEntryType::Refunded ? $fulfillment->refund?->reason : null,
            );
        }

        return $events;
    }

    /**
     * A refund sends the buyer the whole subtotal
     * ({@see \App\Actions\Escrow\IssueRefund}); its ledger entry is the
     * seller's net leaving their balance, which the sentence names beside
     * it. Every other movement is the amount the entry carries.
     */
    private function movementAmount(Fulfillment $fulfillment, LedgerEntry $entry): Money
    {
        return $entry->type === LedgerEntryType::Refunded
            ? $fulfillment->subtotal()
            : Money::fromCents(abs($entry->amount_cents));
    }

    private function movementText(Fulfillment $fulfillment, LedgerEntryType $type): string
    {
        return match ($type) {
            LedgerEntryType::Held => 'held in escrow after the platform fee',
            LedgerEntryType::Released => 'released to your available balance',
            LedgerEntryType::Refunded => 'returned to the buyer · '.$fulfillment->net()->format().' out of your balance',
            LedgerEntryType::PaidOut => 'paid out',
        };
    }
}
