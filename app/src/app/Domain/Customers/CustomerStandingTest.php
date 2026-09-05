<?php

declare(strict_types=1);

namespace App\Domain\Customers;

use App\Domain\DomainRuleViolation;

it('allows a customer with no active block to shop', function (): void {
    CustomerStanding::assertCanShop(null);
})->throwsNoExceptions();

it('refuses a blocked customer, naming the reason', function (): void {
    $assert = fn () => CustomerStanding::assertCanShop('Chargeback fraud.');

    expect($assert)->toThrow(DomainRuleViolation::class, 'Buying is unavailable while your account is blocked: Chargeback fraud.');
});
