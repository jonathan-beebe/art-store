<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\DomainRuleViolation;

/**
 * A serialized unit's own stock movement: `available` claims to `sold`,
 * `sold` restores to `available`. The design's dropped `reserved` state
 * (`docs/item-configurator.md` §2.4, §9) never appears on either side of
 * this — nothing here writes or reads it.
 */
final class UnitSale
{
    private function __construct() {} // @codeCoverageIgnore

    public static function afterSale(UnitState $state): UnitState
    {
        if ($state !== UnitState::Available) {
            throw new DomainRuleViolation('That piece is no longer available.');
        }

        return UnitState::Sold;
    }

    public static function afterRestock(UnitState $state): UnitState
    {
        if ($state !== UnitState::Sold) {
            throw new DomainRuleViolation('That piece was not sold.');
        }

        return UnitState::Available;
    }
}
