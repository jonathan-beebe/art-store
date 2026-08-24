<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Domain\DomainRuleViolation;
use App\Models\CustomerBlock;

it('blocks a customer with a reason', function (): void {
    $customer = $this->verifiedCustomer();

    $block = app(BlockCustomer::class)($customer, 'Chargeback fraud.');

    expect($block->customer_id)->toBe($customer->id)
        ->and($block->reason)->toBe('Chargeback fraud.')
        ->and($block->isActive())->toBeTrue()
        ->and($customer->canShop())->toBeFalse();
});

it('refuses a customer who is already blocked', function (): void {
    $customer = $this->verifiedCustomer();
    CustomerBlock::factory()->create(['customer_id' => $customer->id]);

    $block = fn () => app(BlockCustomer::class)($customer, 'Second reason.');

    expect($block)->toThrow(DomainRuleViolation::class, 'This customer is already blocked.')
        ->and(CustomerBlock::count())->toBe(1);
});

it('blocks a customer again once an earlier block was lifted', function (): void {
    $customer = $this->verifiedCustomer();
    CustomerBlock::factory()->lifted()->create(['customer_id' => $customer->id]);

    $block = app(BlockCustomer::class)($customer, 'Repeat offense.');

    expect($block->reason)->toBe('Repeat offense.')
        ->and(CustomerBlock::count())->toBe(2);
});
