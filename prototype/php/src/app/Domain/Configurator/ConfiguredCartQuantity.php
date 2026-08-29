<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\DomainRuleViolation;
use InvalidArgumentException;

/**
 * {@see \App\Domain\Cart\CartQuantity} for a configured line: a serialized
 * variant claims exactly one unit no matter the requested quantity, an
 * ordinary variant caps at its own tracked quantity (or none, if uncapped),
 * and an unavailable variant — not offered, or nothing left — is refused the
 * same way a listing that is no longer for sale is.
 */
final class ConfiguredCartQuantity
{
    private function __construct() {} // @codeCoverageIgnore

    public static function withinStock(int $requested, bool $isAvailable, bool $isSerialized, ?int $variantQuantity): int
    {
        if ($requested < 1) {
            throw new InvalidArgumentException("A cart holds at least one of a configuration, got {$requested}.");
        }

        if (! $isAvailable) {
            throw new DomainRuleViolation('That configuration is no longer available.');
        }

        if ($isSerialized) {
            return min($requested, 1);
        }

        return $variantQuantity === null ? $requested : min($requested, $variantQuantity);
    }
}
