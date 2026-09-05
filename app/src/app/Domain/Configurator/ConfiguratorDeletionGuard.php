<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\DomainRuleViolation;

/**
 * An axis, option value, or variant the schema would otherwise delete out
 * from under something that still names it: cascade-deleting an axis or
 * option value would silently leave a variant with a gap in its axis
 * coverage, or a stale price nobody chose any more; nulling a variant's id
 * out of a cart or order row (the foreign key is nullable, not restricting)
 * would silently reprice that row as unconfigured. Deleting any of the
 * three is refused while the dependent row still exists. The same rule
 * refuses a sale a listing's stock cannot cover, keeping stock at zero or
 * above.
 */
final class ConfiguratorDeletionGuard
{
    private function __construct() {} // @codeCoverageIgnore

    public static function forAxis(bool $referencedByVariant): void
    {
        if ($referencedByVariant) {
            throw new DomainRuleViolation('This axis has a variant built from one of its values; remove or reassign that variant on Combinations & stock first.');
        }
    }

    public static function forOptionValue(bool $referencedByVariant): void
    {
        if ($referencedByVariant) {
            throw new DomainRuleViolation('This option value is selected by a variant; remove that variant on Combinations & stock first.');
        }
    }

    public static function forVariant(bool $referencedByCartOrOrder): void
    {
        if ($referencedByCartOrOrder) {
            throw new DomainRuleViolation('This combination is in a cart or an order; turn off "Offered" instead of removing it.');
        }
    }
}
