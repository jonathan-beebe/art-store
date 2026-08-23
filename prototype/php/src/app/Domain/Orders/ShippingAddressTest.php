<?php

declare(strict_types=1);

namespace App\Domain\Orders;

it('keeps the address it was given', function (): void {
    $address = ShippingAddress::to(
        name: 'Ada Lovelace',
        line1: '12 Analytical Way',
        line2: 'Flat 4',
        city: 'London',
        region: 'Greater London',
        postalCode: 'EC1A 1BB',
        country: 'GB',
    );

    expect($address->name)->toBe('Ada Lovelace')
        ->and($address->line1)->toBe('12 Analytical Way')
        ->and($address->line2)->toBe('Flat 4')
        ->and($address->city)->toBe('London')
        ->and($address->region)->toBe('Greater London')
        ->and($address->postalCode)->toBe('EC1A 1BB')
        ->and($address->country)->toBe('GB');
});

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
