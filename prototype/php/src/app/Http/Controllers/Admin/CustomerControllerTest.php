<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Customers\BlockCustomer;
use App\Models\Customer;

it('lists every customer with their standing', function (): void {
    $blocked = Customer::factory()->create(['name' => 'Ada Painter']);
    app(BlockCustomer::class)($blocked, 'Chargeback fraud.');
    $ok = Customer::factory()->create(['name' => 'Priya Shopper']);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/customers');

    $response->assertOk();
    $response->assertSeeInOrder(['Ada Painter', 'Blocked']);
    $response->assertSeeInOrder(['Priya Shopper', 'OK']);
});

it('shows one customer with their orders and current standing', function (): void {
    $customer = Customer::factory()->create(['name' => 'Ada Painter']);
    $this->orderFor($customer, $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/customers/{$customer->id}");

    $response->assertOk();
    $response->assertSee('Ada Painter');
    $response->assertSee('Not blocked.');
});

it('shows the block reason and a lift form for a blocked customer', function (): void {
    $customer = Customer::factory()->create();
    app(BlockCustomer::class)($customer, 'Chargeback fraud.');

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/customers/{$customer->id}");

    $response->assertSee('Chargeback fraud.');
    $response->assertSee('Lift block');
});

it('sends a guest to the admin login page', function (): void {
    $response = $this->get('/admin/customers');

    $response->assertRedirect(route('auth.admin.login'));
});
