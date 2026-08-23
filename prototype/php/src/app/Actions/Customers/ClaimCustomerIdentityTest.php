<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\CustomerMerge;

it('creates a verified customer when nobody has claimed the address and no cookie exists', function (): void {
    $customer = app(ClaimCustomerIdentity::class)('shopper@example.com', null, $this->moment('2026-08-20 09:00:00'));

    expect(Customer::sole()->is($customer))->toBeTrue()
        ->and($customer->email)->toBe('shopper@example.com')
        ->and($customer->email_verified_at)->not->toBeNull();
});

it('signs an existing owner in without creating a second row', function (): void {
    $owner = Customer::factory()->create(['email' => 'shopper@example.com', 'email_verified_at' => null]);

    $customer = app(ClaimCustomerIdentity::class)('shopper@example.com', null, $this->moment('2026-08-20 09:00:00'));

    expect(Customer::count())->toBe(1)
        ->and($customer->is($owner))->toBeTrue()
        ->and($customer->refresh()->email_verified_at)->not->toBeNull();
});

it('claims the anonymous row the cookie points at when nobody else owns the address', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();

    $customer = app(ClaimCustomerIdentity::class)('shopper@example.com', $anonymous, $this->moment('2026-08-20 09:00:00'));

    expect($customer->is($anonymous))->toBeTrue()
        ->and($customer->email)->toBe('shopper@example.com');
});

it('merges the anonymous row into the account that already owns the address', function (): void {
    $owner = Customer::factory()->create(['email' => 'shopper@example.com']);
    $anonymous = Customer::factory()->anonymous()->create();

    $customer = app(ClaimCustomerIdentity::class)('shopper@example.com', $anonymous, $this->moment('2026-08-20 09:00:00'));

    expect($customer->is($owner))->toBeTrue();
    $this->assertDatabaseHas('customer_merges', [
        'anonymous_customer_id' => $anonymous->id,
        'customer_id' => $owner->id,
    ]);
});

it('signs the same customer in when the cookie already points at the address owner', function (): void {
    $customer = Customer::factory()->create(['email' => 'shopper@example.com']);

    $result = app(ClaimCustomerIdentity::class)('shopper@example.com', $customer, $this->moment('2026-08-20 09:00:00'));

    expect($result->is($customer))->toBeTrue()
        ->and(CustomerMerge::count())->toBe(0);
});
