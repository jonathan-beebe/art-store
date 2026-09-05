<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerMerge;
use App\Models\Favorite;
use Tests\CapturedStory;

it('deletes a bare old anonymous row and its empty cart', function (): void {
    $customer = Customer::factory()->anonymous()->create(['created_at' => $this->moment('2026-07-01 00:00:00')]);
    $cart = Cart::create(['customer_id' => $customer->id]);

    $deleted = app(SweepAnonymousCustomers::class)($this->moment('2026-08-01 00:00:00'), 30);

    expect($deleted)->toBe(1)
        ->and(Customer::find($customer->id))->toBeNull()
        ->and(Cart::find($cart->id))->toBeNull();
});

it('keeps an old anonymous row that holds a favorite', function (): void {
    $customer = Customer::factory()->anonymous()->create(['created_at' => $this->moment('2026-07-01 00:00:00')]);
    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => $this->listing($this->seller())->id]);

    $deleted = app(SweepAnonymousCustomers::class)($this->moment('2026-08-01 00:00:00'), 30);

    expect($deleted)->toBe(0)
        ->and(Customer::find($customer->id))->not->toBeNull();
});

it('keeps an old anonymous row that holds an order', function (): void {
    $customer = Customer::factory()->anonymous()->create(['created_at' => $this->moment('2026-07-01 00:00:00')]);
    $this->orderFor($customer, $this->listing($this->seller()));

    $deleted = app(SweepAnonymousCustomers::class)($this->moment('2026-08-01 00:00:00'), 30);

    expect($deleted)->toBe(0)
        ->and(Customer::find($customer->id))->not->toBeNull();
});

it('keeps an old anonymous row a recorded merge still points at', function (): void {
    $anonymous = Customer::factory()->anonymous()->create(['created_at' => $this->moment('2026-07-01 00:00:00')]);
    $verified = $this->verifiedCustomer();
    CustomerMerge::factory()->create(['anonymous_customer_id' => $anonymous->id, 'customer_id' => $verified->id]);

    $deleted = app(SweepAnonymousCustomers::class)($this->moment('2026-08-01 00:00:00'), 30);

    expect($deleted)->toBe(0)
        ->and(Customer::find($anonymous->id))->not->toBeNull();
});

it('keeps a verified customer named on the customer side of a merge, even bare', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create(['created_at' => $this->moment('2026-07-01 00:00:00')]);
    CustomerMerge::factory()->create(['anonymous_customer_id' => $anonymous->id, 'customer_id' => $verified->id]);

    app(SweepAnonymousCustomers::class)($this->moment('2026-08-01 00:00:00'), 30);

    expect(Customer::find($verified->id))->not->toBeNull();
});

it('leaves an anonymous row younger than the retention window alone', function (): void {
    $customer = Customer::factory()->anonymous()->create(['created_at' => $this->moment('2026-07-25 00:00:00')]);

    $deleted = app(SweepAnonymousCustomers::class)($this->moment('2026-08-01 00:00:00'), 30);

    expect($deleted)->toBe(0)
        ->and(Customer::find($customer->id))->not->toBeNull();
});

it('never sweeps a verified customer, however old and bare', function (): void {
    $customer = Customer::factory()->create(['created_at' => $this->moment('2026-07-01 00:00:00')]);

    app(SweepAnonymousCustomers::class)($this->moment('2026-08-01 00:00:00'), 30);

    expect(Customer::find($customer->id))->not->toBeNull();
});

it('sweeps an anonymous row created exactly at the cutoff instant', function (): void {
    $customer = Customer::factory()->anonymous()->create(['created_at' => $this->moment('2026-07-01 00:00:00')]);

    $deleted = app(SweepAnonymousCustomers::class)($this->moment('2026-07-31 00:00:00'), 30);

    expect($deleted)->toBe(0)
        ->and(Customer::find($customer->id))->not->toBeNull();
});

it('finds nothing left on a second run', function (): void {
    Customer::factory()->anonymous()->create(['created_at' => $this->moment('2026-07-01 00:00:00')]);

    app(SweepAnonymousCustomers::class)($this->moment('2026-08-01 00:00:00'), 30);

    expect(app(SweepAnonymousCustomers::class)($this->moment('2026-08-01 01:00:00'), 30))->toBe(0);
});

it('tells the sweep as the system, with the cutoff and the count', function (): void {
    Customer::factory()->anonymous()->create(['created_at' => $this->moment('2026-07-01 00:00:00')]);
    $log = CapturedStory::capture();

    app(SweepAnonymousCustomers::class)($this->moment('2026-08-01 00:00:00'), 30);

    expect($log->values('phase', 'customer.sweep'))->toBe(['will', 'did'])
        ->and($log->line('customer.sweep', 'will')['data'])->toHaveKey('cutoff')
        ->and($log->line('customer.sweep', 'did')['data'])->toMatchArray(['deleted_count' => 1])
        ->and($log->values('actor_type', 'customer.sweep'))->toBe(['system', 'system']);
});
