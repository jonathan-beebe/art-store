<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Models\Customer;
use App\Models\Order;

$fillCart = function (): void {
    test()->listing(test()->seller(), ['slug' => 'harbour-at-dawn', 'price_cents' => 24500]);
    test()->post('/cart/harbour-at-dawn');
};

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
$form = fn (array $overrides = []): array => $overrides + [
    'email' => 'guest@example.com',
    'shipping_name' => 'Ada Lovelace',
    'shipping_line1' => '12 Analytical Way',
    'shipping_city' => 'London',
    'shipping_region' => 'Greater London',
    'shipping_postal_code' => 'EC1A 1BB',
    'shipping_country' => 'GB',
];

it('refuses a checkout missing something the order needs', function (array $overrides, string $field) use ($fillCart, $form): void {
    $this->visitor();
    $fillCart();

    $response = $this->post('/checkout', $form($overrides));

    $response->assertSessionHasErrors($field);
    expect(Order::count())->toBe(0);
})->with([
    'no email address' => [['email' => ''], 'email'],
    'an address that is not an email address' => [['email' => 'not-an-address'], 'email'],
    'no name to ship to' => [['shipping_name' => ''], 'shipping_name'],
    'no street address' => [['shipping_line1' => ''], 'shipping_line1'],
    'no city' => [['shipping_city' => ''], 'shipping_city'],
    'no region' => [['shipping_region' => ''], 'shipping_region'],
    'no postal code' => [['shipping_postal_code' => ''], 'shipping_postal_code'],
    'no country' => [['shipping_country' => ''], 'shipping_country'],
    'a postal code longer than the column' => [['shipping_postal_code' => str_repeat('X', 33)], 'shipping_postal_code'],
]);

it('requires a verified customer to give a card', function () use ($fillCart, $form): void {
    $this->actingAs(Customer::factory()->create(), 'customer');
    $fillCart();

    $response = $this->post('/checkout', $form());

    $response->assertSessionHasErrors('card_number');
    expect(Order::count())->toBe(0);
});

it('asks a guest for no card', function () use ($fillCart, $form): void {
    $this->visitor();
    $fillCart();

    $response = $this->post('/checkout', $form());

    $response->assertSessionHasNoErrors();
    expect(Order::count())->toBe(1);
});

it('builds the shipping address from the fields the shopper typed', function () use ($form): void {
    $request = CheckoutRequest::create('/checkout', 'POST', $form(['shipping_line2' => 'Flat 4']));

    expect($request->toShippingAddress()->attributes())->toBe([
        'shipping_name' => 'Ada Lovelace',
        'shipping_line1' => '12 Analytical Way',
        'shipping_line2' => 'Flat 4',
        'shipping_city' => 'London',
        'shipping_region' => 'Greater London',
        'shipping_postal_code' => 'EC1A 1BB',
        'shipping_country' => 'GB',
    ]);
});

it('leaves an unused second address line null', function (?string $submitted) use ($form): void {
    $request = CheckoutRequest::create('/checkout', 'POST', $form(['shipping_line2' => $submitted]));

    expect($request->toShippingAddress()->line2)->toBeNull();
})->with([
    'a field the form left empty' => [''],
    'a field the browser did not send' => [null],
]);

it('buys a guest under the address they typed', function () use ($form): void {
    $visitor = $this->anonymousCustomer();
    $request = CheckoutRequest::create('/checkout', 'POST', $form(['email' => ' Guest@Example.COM ']));

    $purchaser = $request->toPurchaser($visitor);

    expect($purchaser->customerId)->toBe($visitor->id)
        ->and($purchaser->email)->toBe('guest@example.com')
        ->and($purchaser->isEmailVerified())->toBeFalse();
});

it('buys a verified customer under the address on their account', function () use ($form): void {
    $visitor = Customer::factory()->create(['email' => 'shopper@example.com']);

    $purchaser = CheckoutRequest::create('/checkout', 'POST', $form(['email' => 'someone-else@example.com']))
        ->toPurchaser($visitor);

    expect($purchaser->email)->toBe('shopper@example.com')
        ->and($purchaser->isEmailVerified())->toBeTrue();
});
