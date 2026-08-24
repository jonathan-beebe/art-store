<?php

declare(strict_types=1);

namespace App\Actions\Escrow;

use App\Domain\Auth\ActorType;
use App\Domain\DomainRuleViolation;
use App\Domain\Escrow\LedgerMovement;
use App\Events\RefundIssued;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Models\Refund;
use DateTimeImmutable;

/**
 * Sends one fulfillment's subtotal back to the customer: the `refunds` row,
 * the reversing ledger entry, and the order's running refunded total.
 *
 * It opens no transaction and tells no story of its own. A seller's decline
 * and an admin's refund both call it from inside theirs, so the money moving
 * back commits with the state change that caused it, and the `refund.issue`
 * line belongs to the action the person asked for.
 */
final readonly class IssueRefund
{
    public function __invoke(
        Fulfillment $fulfillment,
        ActorType $issuer,
        string $issuerId,
        string $reason,
        DateTimeImmutable $now,
    ): Refund {
        $order = $fulfillment->loadMissing('order')->order;

        if (! $order->status->hasBeenPaid()) {
            throw new DomainRuleViolation('An order that has not been paid has nothing to refund.');
        }

        $refund = Refund::create([
            'order_id' => $order->id,
            'fulfillment_id' => $fulfillment->id,
            'payment_id' => $order->approvedPayment()->first()?->id,
            'amount_cents' => $fulfillment->subtotal_cents,
            'reason' => $reason,
            'issued_by_type' => $issuer->value,
            'issued_by_id' => $issuerId,
        ]);

        // The seller keeps neither the sale nor the platform's cut of it: the
        // entry runs the whole net back out of wherever that fulfillment's
        // money is sitting, and the fee is forgone rather than collected.
        $movement = LedgerMovement::refund($fulfillment->net());

        LedgerEntry::create([
            'seller_id' => $fulfillment->seller_id,
            'fulfillment_id' => $fulfillment->id,
            'type' => $movement->type,
            'amount_cents' => $movement->amount->cents,
            'occurred_at' => $now,
        ]);

        $order->increment('refunded_cents', $refund->amount_cents);

        RefundIssued::dispatch($refund, $now);

        return $refund;
    }
}
