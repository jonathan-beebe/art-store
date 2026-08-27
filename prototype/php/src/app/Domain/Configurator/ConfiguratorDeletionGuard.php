<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\DomainRuleViolation;

/**
 * An axis or option value the schema would otherwise cascade-delete out from
 * under a variant that still names it — silently leaving that variant with a
 * gap in its axis coverage, or a stale price nobody chose any more. Deleting
 * either here is refused instead while a variant still selects it, the same
 * way a sale a listing's stock cannot cover is refused rather than let
 * negative.
 */
final class ConfiguratorDeletionGuard
{
    private function __construct() {} // @codeCoverageIgnore

    public static function forAxis(bool $referencedByVariant): void
    {
        if ($referencedByVariant) {
            throw new DomainRuleViolation('This axis has a variant built from one of its values; remove or reassign that variant first.');
        }
    }

    public static function forOptionValue(bool $referencedByVariant): void
    {
        if ($referencedByVariant) {
            throw new DomainRuleViolation('This option value is selected by a variant; remove that variant first.');
        }
    }
}
