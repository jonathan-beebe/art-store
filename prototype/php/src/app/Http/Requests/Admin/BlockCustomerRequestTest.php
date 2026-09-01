<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Customer;
use App\Models\CustomerBlock;

it('reads the submitted reason', function (): void {
    $request = BlockCustomerRequest::create('/admin/customers/1/blocks', 'POST', ['reason' => 'Chargeback fraud.']);

    expect($request->reason())->toBe('Chargeback fraud.');
});

it('refuses an empty reason or one longer than the column', function (string $reason): void {
    $customer = Customer::factory()->create();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/customers/{$customer->id}/blocks", ['reason' => $reason]);

    $response->assertSessionHasErrors('reason');
    expect(CustomerBlock::count())->toBe(0);
})->with([
    'empty' => [''],
    'a reason longer than the column' => [str_repeat('a', 501)],
]);

it('accepts a reason at exactly the column limit', function (): void {
    $customer = Customer::factory()->create();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/customers/{$customer->id}/blocks", ['reason' => str_repeat('a', 500)]);

    $response->assertSessionHasNoErrors();
    expect(strlen(CustomerBlock::sole()->reason))->toBe(500);
});
