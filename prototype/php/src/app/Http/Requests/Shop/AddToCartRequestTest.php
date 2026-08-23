<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Models\CartItem;

it('takes one of the listing when the form sends no quantity', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $this->post('/cart/harbour-at-dawn');

    expect(CartItem::sole()->quantity)->toBe(1);
});

it('takes the quantity the form sends', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'quantity' => 5]);

    $this->post('/cart/harbour-at-dawn', ['quantity' => 3]);

    expect(CartItem::sole()->quantity)->toBe(3);
});

it('refuses a quantity that is not a whole number of pieces', function (string|int $quantity): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'quantity' => 5]);

    $response = $this->post('/cart/harbour-at-dawn', ['quantity' => $quantity]);

    $response->assertSessionHasErrors('quantity');
    expect(CartItem::count())->toBe(0);
})->with([
    'none at all' => [0],
    'a negative count' => [-1],
    'a word' => ['two'],
]);
