<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Domain\DomainRuleViolation;
use App\Models\CustomerBlock;

it('lifts the active block', function (): void {
    $customer = $this->verifiedCustomer();
    CustomerBlock::factory()->create(['customer_id' => $customer->id]);

    $block = app(LiftCustomerBlock::class)($customer, $this->moment('2026-08-23 10:00:00'));

    expect($block->lifted_at?->format('Y-m-d H:i:s'))->toBe('2026-08-23 10:00:00')
        ->and($customer->canShop())->toBeTrue();
});

it('refuses a customer who is not blocked', function (): void {
    $customer = $this->verifiedCustomer();

    $lift = fn () => app(LiftCustomerBlock::class)($customer, $this->moment('2026-08-23 10:00:00'));

    expect($lift)->toThrow(DomainRuleViolation::class, 'This customer is not blocked.');
});

it('refuses a customer whose block was already lifted', function (): void {
    $customer = $this->verifiedCustomer();
    CustomerBlock::factory()->lifted()->create(['customer_id' => $customer->id]);

    $lift = fn () => app(LiftCustomerBlock::class)($customer, $this->moment('2026-08-23 10:00:00'));

    expect($lift)->toThrow(DomainRuleViolation::class, 'This customer is not blocked.');
});
