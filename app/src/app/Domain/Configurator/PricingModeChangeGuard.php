<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\DomainRuleViolation;

/**
 * A choice's pricing mode is answered once, at creation
 * (`docs/item-configurator.md` §3) — changing it once the choice has options
 * would leave those options' prices ambiguous (an `add_on` "+$18.00" option
 * does not obviously become a `standalone` "$18.00" or a "$0.00"). Refused
 * here. Removing the options first is the escape hatch.
 */
final class PricingModeChangeGuard
{
    private function __construct() {} // @codeCoverageIgnore

    public static function forAxis(bool $changingMode, bool $hasOptions): void
    {
        if ($changingMode && $hasOptions) {
            throw new DomainRuleViolation("This choice already has options — its pricing can't change. Remove the options first, or add a new choice.");
        }
    }
}
