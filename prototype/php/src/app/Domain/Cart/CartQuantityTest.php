<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use DomainException;
use InvalidArgumentException;

it('keeps a quantity within stock', function (int $requested, int $stock, int $expected): void {
    expect(CartQuantity::withinStock($requested, $stock))->toBe($expected);
})->with([
    'a quantity the listing can cover' => [2, 5, 2],
    'a quantity capped at the stock on hand' => [9, 5, 5],
]);

it('rejects a listing with nothing left', function (): void {
    expect(fn () => CartQuantity::withinStock(1, 0))->toThrow(DomainException::class);
});

it('rejects a request below one', function (): void {
    expect(fn () => CartQuantity::withinStock(0, 5))->toThrow(InvalidArgumentException::class);
});
