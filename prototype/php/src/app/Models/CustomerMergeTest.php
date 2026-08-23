<?php

declare(strict_types=1);

namespace App\Models;

it('reads the anonymous customer and the customer it was merged into', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $merge = CustomerMerge::create(['anonymous_customer_id' => $anonymous->id, 'customer_id' => $verified->id]);

    expect($merge->anonymousCustomer()->sole()->is($anonymous))->toBeTrue()
        ->and($merge->customer()->sole()->is($verified))->toBeTrue();
});
