<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Customers\BlockCustomer;
use App\Models\Customer;
use App\Models\CustomerBlock;

it('blocks a customer with the submitted reason', function (): void {
    $customer = Customer::factory()->create();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/customers/{$customer->id}/blocks", ['reason' => 'Chargeback fraud.']);

    $response->assertRedirect(route('admin.customers.show', $customer));
    expect(CustomerBlock::sole()->reason)->toBe('Chargeback fraud.')
        ->and($customer->canShop())->toBeFalse();
});

it('refuses a submission without a reason', function (): void {
    $customer = Customer::factory()->create();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/customers/{$customer->id}/blocks", ['reason' => '']);

    $response->assertSessionHasErrors('reason');
    expect(CustomerBlock::count())->toBe(0);
});

it('refuses to block a customer who is already blocked', function (): void {
    $customer = Customer::factory()->create();
    app(BlockCustomer::class)($customer, 'First reason.');

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/customers/{$customer->id}/blocks", ['reason' => 'Second reason.']);

    $response->assertSessionHasErrors();
    expect(CustomerBlock::count())->toBe(1);
});

it('sends a guest to the admin login page', function (): void {
    $customer = Customer::factory()->create();

    $response = $this->post("/admin/customers/{$customer->id}/blocks", ['reason' => 'Chargeback fraud.']);

    $response->assertRedirect(route('auth.admin.login'));
});
