<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\CustomerMerge;

it('resolves nothing for a missing cookie', function (): void {
    expect(app(ResolveCustomerFromCookie::class)(null))->toBeNull();
});

it('resolves nothing for a cookie that is not an integer', function (): void {
    expect(app(ResolveCustomerFromCookie::class)('not-an-id'))->toBeNull();
});

it('resolves nothing for a cookie pointing at a customer that no longer exists', function (): void {
    expect(app(ResolveCustomerFromCookie::class)('999999'))->toBeNull();
});

it('resolves the customer the cookie names directly', function (): void {
    $customer = Customer::factory()->anonymous()->create();

    $resolved = app(ResolveCustomerFromCookie::class)((string) $customer->id);

    expect($resolved?->is($customer))->toBeTrue();
});

it('resolves a stale cookie through one recorded merge', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    CustomerMerge::create(['anonymous_customer_id' => $anonymous->id, 'customer_id' => $verified->id]);

    $resolved = app(ResolveCustomerFromCookie::class)((string) $anonymous->id);

    expect($resolved?->is($verified))->toBeTrue();
});

it('resolves a stale cookie through a chain of merges', function (): void {
    $first = Customer::factory()->anonymous()->create();
    $second = Customer::factory()->create();
    $third = Customer::factory()->create();
    CustomerMerge::create(['anonymous_customer_id' => $first->id, 'customer_id' => $second->id]);
    CustomerMerge::create(['anonymous_customer_id' => $second->id, 'customer_id' => $third->id]);

    $resolved = app(ResolveCustomerFromCookie::class)((string) $first->id);

    expect($resolved?->is($third))->toBeTrue();
});

it('stops rather than looping forever on a cyclical chain of merges', function (): void {
    $first = Customer::factory()->anonymous()->create();
    $second = Customer::factory()->create();
    CustomerMerge::create(['anonymous_customer_id' => $first->id, 'customer_id' => $second->id]);
    CustomerMerge::create(['anonymous_customer_id' => $second->id, 'customer_id' => $first->id]);

    $resolved = app(ResolveCustomerFromCookie::class)((string) $first->id);

    expect($resolved)->not->toBeNull();
});
