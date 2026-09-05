<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;
use InvalidArgumentException;

/**
 * One quantity-break tier: at `minQty` or more, the resolved unit price
 * carries a `discountBps` (basis points, 1–9999) discount. `bestFor` is the
 * one tier a resolved cart line applies — the highest `minQty` the quantity
 * in hand clears — so one tier table works across every configuration of the
 * product the way docs/…-design-doc.md §2.2 asks for.
 */
final readonly class QuantityDiscount
{
    private const int BASIS_POINTS = 10_000;

    private function __construct(public int $minQty, public int $discountBps) {}

    public static function of(int $minQty, int $discountBps): self
    {
        if ($minQty < 2) {
            throw new InvalidArgumentException("A quantity break applies at 2 or more, got {$minQty}.");
        }

        if ($discountBps < 1 || $discountBps > 9999) {
            throw new InvalidArgumentException("A quantity break's discount must be between 1 and 9999 basis points, got {$discountBps}.");
        }

        return new self($minQty, $discountBps);
    }

    /**
     * @param  list<self>  $breaks
     */
    public static function bestFor(array $breaks, int $quantity): ?self
    {
        $applicable = array_values(array_filter($breaks, fn (self $break): bool => $quantity >= $break->minQty));

        if ($applicable === []) {
            return null;
        }

        usort($applicable, fn (self $a, self $b): int => $b->minQty <=> $a->minQty);

        return $applicable[0];
    }

    /**
     * The amount this tier shaves off the given subtotal, rounded half away
     * from zero the way `Money::percent()` rounds — a discount never
     * under-shaves a cent in the seller's favor.
     */
    public function discountFor(Money $subtotal): Money
    {
        $scaled = $subtotal->cents * $this->discountBps;

        return Money::fromCents(intdiv($scaled + (self::BASIS_POINTS / 2), self::BASIS_POINTS));
    }
}
