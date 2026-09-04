<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\Seller;

it('lets a seller view a buyer holding a paid parcel with them', function (): void {
    $seller = $this->seller();
    $customer = Customer::factory()->create();
    $this->paidFulfillmentFor($seller, $customer);

    expect((new CustomerPolicy)->view($seller, $customer)->allowed())->toBeTrue();
});

it('answers not found for a buyer holding nothing with the seller', function (): void {
    $seller = $this->seller();
    $customer = Customer::factory()->create();

    $response = (new CustomerPolicy)->view($seller, $customer);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
});

it('answers not found for a buyer holding a parcel with another seller', function (): void {
    $seller = $this->seller();
    $customer = Customer::factory()->create();
    $this->paidFulfillmentFor(Seller::factory()->create(), $customer);

    $response = (new CustomerPolicy)->view($seller, $customer);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
});
