<?php

declare(strict_types=1);

namespace App\Domain\Orders;

it('names every column an order stores it in', function (): void {
    $address = ShippingAddress::to(
        name: 'Ada Lovelace',
        line1: '12 Analytical Way',
        line2: null,
        city: 'London',
        region: 'Greater London',
        postalCode: 'EC1A 1BB',
        country: 'GB',
    );

    expect($address->attributes())->toBe([
        'shipping_name' => 'Ada Lovelace',
        'shipping_line1' => '12 Analytical Way',
        'shipping_line2' => null,
        'shipping_city' => 'London',
        'shipping_region' => 'Greater London',
        'shipping_postal_code' => 'EC1A 1BB',
        'shipping_country' => 'GB',
    ]);
});
