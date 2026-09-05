<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\DomainRuleViolation;

/**
 * An option on a `standalone` axis carries its own absolute price
 * (`docs/item-configurator.md` §3), so adding or updating one with no price
 * is refused. An option on an `add_on` axis carries a surcharge and needs
 * none.
 */
final class StandaloneOptionPriceGuard
{
    private function __construct() {} // @codeCoverageIgnore

    public static function forOption(PricingMode $mode, ?int $priceCents): void
    {
        if ($mode->isStandalone() && $priceCents === null) {
            throw new DomainRuleViolation('Every option on this choice needs its own price.');
        }
    }
}
