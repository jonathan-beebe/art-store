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
use App\Models\OrderItem;
use App\Models\Payment;

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
        $label = $this->itemLabel($fulfillment);

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
                actor: Money::fromCents(abs($entry->amount_cents))->format(),
                text: $this->movementText($entry->type),
                quote: $entry->type === LedgerEntryType::Refunded ? $fulfillment->refund?->reason : null,
            );
        }

        return $events;
    }

    private function movementText(LedgerEntryType $type): string
    {
        return match ($type) {
            LedgerEntryType::Held => 'held in escrow after the platform fee',
            LedgerEntryType::Released => 'released to your available balance',
            LedgerEntryType::Refunded => 'returned to the buyer',
            LedgerEntryType::PaidOut => 'paid out',
        };
    }

    /**
     * The seller's own lines on the order, as one phrase.
     */
    private function itemLabel(Fulfillment $fulfillment): string
    {
        $items = $fulfillment->order->items
            ->where('seller_id', $fulfillment->seller_id)
            ->values();

        $first = $items->first();

        if (! $first instanceof OrderItem) {
            return 'no items';
        }

        $label = $first->quantity > 1 ? "{$first->title} ×{$first->quantity}" : $first->title;
        $rest = $items->count() - 1;

        return $rest > 0 ? "{$label} +{$rest} more" : $label;
    }
}
