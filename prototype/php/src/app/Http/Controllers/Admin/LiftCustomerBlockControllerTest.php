<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Customers\BlockCustomer;
use App\Models\Customer;

it('lifts an active block', function (): void {
    $customer = Customer::factory()->create();
    app(BlockCustomer::class)($customer, 'Chargeback fraud.');

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/customers/{$customer->id}/blocks/lift");

    $response->assertRedirect(route('admin.customers.show', $customer));
    expect($customer->canShop())->toBeTrue();
});

it('refuses to lift a block on a customer who is not blocked', function (): void {
    $customer = Customer::factory()->create();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/customers/{$customer->id}/blocks/lift");

    $response->assertSessionHasErrors();
});

it('sends a guest to the admin login page', function (): void {
    $customer = Customer::factory()->create();

    $response = $this->post("/admin/customers/{$customer->id}/blocks/lift");

    $response->assertRedirect(route('auth.admin.login'));
});
