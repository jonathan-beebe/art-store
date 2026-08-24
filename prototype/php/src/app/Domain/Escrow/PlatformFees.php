<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;

/**
 * What the platform kept out of every sale and what it gave back. A fee is
 * priced at placement and earned when a fulfillment settles: `isLive()`
 * fulfillments have earned theirs, and a declined or refunded one forgoes
 * it — the `refunded` ledger entry already ran the whole net back out, so
 * the fee was never really collected.
 */
final readonly class PlatformFees
{
    private function __construct(public Money $earned, public Money $refunded) {}

    /**
     * @param  list<array{status: FulfillmentStatus, feeCents: int}>  $fulfillments
     */
    public static function from(array $fulfillments): self
    {
        $earned = Money::zero();
        $refunded = Money::zero();

        foreach ($fulfillments as $fulfillment) {
            $fee = Money::fromCents($fulfillment['feeCents']);

            if ($fulfillment['status']->isLive()) {
                $earned = $earned->add($fee);
            } else {
                $refunded = $refunded->add($fee);
            }
        }

        return new self($earned, $refunded);
    }
}
