<?php

declare(strict_types=1);

namespace App\Domain\Customers;

use App\Domain\DomainRuleViolation;

final class CustomerStanding
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * A blocked customer reads why on the page they submitted from, in the
     * admin's own words for the block.
     *
     * @param  string|null  $blockReason  null when the customer is not blocked
     */
    public static function assertCanShop(?string $blockReason): void
    {
        if ($blockReason !== null) {
            throw new DomainRuleViolation("Buying is unavailable while your account is blocked: {$blockReason}");
        }
    }
}
