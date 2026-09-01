<?php

declare(strict_types=1);

namespace App\Domain\Money;

use InvalidArgumentException;

it('holds the cents it was built from', function (): void {
    expect(Money::fromCents(1234)->cents)->toBe(1234);
});

it('adds another amount', function (): void {
    $sum = Money::fromCents(1234)->add(Money::fromCents(66));

    expect($sum->cents)->toBe(1300);
});

it('starts at zero', function (): void {
    expect(Money::zero()->cents)->toBe(0);
});

it('subtracts another amount', function (): void {
    expect(Money::fromCents(1300)->subtract(Money::fromCents(66))->cents)->toBe(1234);
});

it('subtracts past zero into a negative amount', function (): void {
    expect(Money::zero()->subtract(Money::fromCents(66))->cents)->toBe(-66);
});

it('equals another amount of the same cents', function (): void {
    expect(Money::fromCents(1234)->equals(Money::fromCents(1234)))->toBeTrue()
        ->and(Money::fromCents(1234)->equals(Money::fromCents(1235)))->toBeFalse();
});

it('reads whether it is above zero', function (int $cents, bool $isPositive, bool $isZero): void {
    expect(Money::fromCents($cents)->isPositive())->toBe($isPositive)
        ->and(Money::fromCents($cents)->isZero())->toBe($isZero);
})->with([
    'an amount to pay out' => [9000, true, false],
    'nothing' => [0, false, true],
    'an amount owed' => [-9000, false, false],
]);

it('renders as its formatted amount in a string', function (): void {
    expect((string) Money::fromCents(1234))->toBe('$12.34');
});

it('multiplies by a quantity', function (): void {
    expect(Money::fromCents(1234)->multiply(3)->cents)->toBe(3702);
});

it('rejects a negative quantity', function (): void {
    expect(fn () => Money::fromCents(1234)->multiply(-1))->toThrow(InvalidArgumentException::class);
});

it('takes a percentage, rounding half a cent away from zero', function (int $cents, int $percent, int $expected): void {
    expect(Money::fromCents($cents)->percent($percent)->cents)->toBe($expected);
})->with([
    'a whole-cent percentage' => [1230, 10, 123],
    // 10% of 1235 is 123.5 cents; the platform fee never under-collects.
    'a positive half-cent percentage rounds up' => [1235, 10, 124],
    'a negative half-cent percentage rounds away from zero' => [-1235, 10, -124],
]);

it('rejects a percentage outside zero to one hundred', function (): void {
    expect(fn () => Money::fromCents(1234)->percent(101))->toThrow(InvalidArgumentException::class);
});

it('formats as dollars and cents', function (int $cents, string $expected): void {
    expect(Money::fromCents($cents)->format())->toBe($expected);
})->with([
    'dollars and cents' => [1234, '$12.34'],
    'thousands with a separator' => [123456789, '$1,234,567.89'],
    'a negative amount with a leading sign' => [-1234, '-$12.34'],
    'zero' => [0, '$0.00'],
]);

it('reads a price typed into a price field', function (string $price, int $expected): void {
    expect(Money::fromDollars($price)->cents)->toBe($expected);
})->with([
    'dollars and cents' => ['12.34', 1234],
    'whole dollars' => ['12', 1200],
    'a single decimal place, padded' => ['12.5', 1250],
    'surrounding whitespace' => [' 12.34 ', 1234],
    'a price too large for a float to hold exactly' => ['80704505322479.28', 8070450532247928],
]);

it('rejects an invalid price', function (string $price): void {
    expect(fn () => Money::fromDollars($price))->toThrow(InvalidArgumentException::class);
})->with([
    'not a number' => ['twelve'],
    'more than two decimal places' => ['12.345'],
]);
