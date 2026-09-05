<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Money\Money;

final readonly class LedgerBalance
{
    private function __construct(
        public Money $held,
        public Money $available,
        public Money $paidOut,
        public Money $refunded,
    ) {}

    public function isPayable(): bool
    {
        return $this->available->isPositive();
    }

    /**
     * The fold, taken one fulfillment at a time. Hold, release, and refund
     * all name a fulfillment, and a refund takes the money back from
     * wherever that fulfillment's money is sitting: out of escrow while it is
     * still held, out of the available balance once delivery released it.
     * Which of the two it is cannot be read from a seller's totals alone —
     * one sale refunded after release and another still held sum to the same
     * three numbers as the reverse — so the movements are grouped by
     * fulfillment before they are added up. Payouts name no fulfillment and
     * fall into a group of their own.
     *
     * A refund larger than what escrow still holds leaves the available
     * balance negative. That is the intended reading: the seller owes the
     * platform, and the next payout period nets it off (`isPayable()` is
     * false, so nothing is paid and the negative carries forward).
     *
     * @param  list<LedgerMovement>  $movements
     */
    public static function from(array $movements): self
    {
        $held = Money::zero();
        $available = Money::zero();
        $refundedTotal = Money::zero();

        foreach (self::byFulfillment($movements) as $entries) {
            $released = self::total($entries, LedgerEntryType::Released);
            $refunded = self::total($entries, LedgerEntryType::Refunded);
            $escrow = self::total($entries, LedgerEntryType::Held)->subtract($released);
            $fromEscrow = Money::fromCents(max(0, min($escrow->cents, -$refunded->cents)));

            $held = $held->add($escrow)->subtract($fromEscrow);
            $available = $available
                ->add($released)
                ->add(self::total($entries, LedgerEntryType::PaidOut))
                ->add($refunded)
                ->add($fromEscrow);
            // A refund movement is stored as the negative of what it sends
            // back (`LedgerMovement::refund()`), so the running total reads
            // as a positive amount refunded.
            $refundedTotal = $refundedTotal->subtract($refunded);
        }

        return new self(
            $held,
            $available,
            Money::zero()->subtract(self::total($movements, LedgerEntryType::PaidOut)),
            $refundedTotal,
        );
    }

    /**
     * Several balances added together, field by field. Each one already
     * folded its own fulfillments, and a fulfillment belongs to exactly one
     * seller. Grouping by fulfillment across every seller at once (`from()`
     * on the whole ledger) partitions into the same groups as folding each
     * seller alone and adding the results. A platform total reuses balances
     * a page already computed. The ledger is read once.
     *
     * @param  list<self>  $balances
     */
    public static function combine(array $balances): self
    {
        return array_reduce(
            $balances,
            fn (self $sum, self $balance): self => new self(
                $sum->held->add($balance->held),
                $sum->available->add($balance->available),
                $sum->paidOut->add($balance->paidOut),
                $sum->refunded->add($balance->refunded),
            ),
            self::from([]),
        );
    }

    /**
     * @param  list<LedgerMovement>  $movements
     * @return list<list<LedgerMovement>>
     */
    private static function byFulfillment(array $movements): array
    {
        $groups = [];

        foreach ($movements as $movement) {
            $groups[$movement->fulfillmentId ?? ''][] = $movement;
        }

        return array_values($groups);
    }

    /**
     * @param  list<LedgerMovement>  $movements
     */
    private static function total(array $movements, LedgerEntryType $type): Money
    {
        return array_reduce(
            array_filter($movements, fn (LedgerMovement $movement): bool => $movement->type === $type),
            fn (Money $sum, LedgerMovement $movement): Money => $sum->add($movement->amount),
            Money::zero(),
        );
    }
}
