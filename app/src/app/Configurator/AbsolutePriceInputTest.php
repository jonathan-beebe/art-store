<?php

declare(strict_types=1);

namespace App\Configurator;

use InvalidArgumentException;

it('parses a plain dollar amount', function (): void {
    expect(AbsolutePriceInput::parseCents('18.00'))->toBe(1800);
});

it('strips a leading dollar sign', function (): void {
    expect(AbsolutePriceInput::parseCents('$18.00'))->toBe(1800);
});

it('refuses a negative amount', function (): void {
    AbsolutePriceInput::parseCents('-5.00');
})->throws(InvalidArgumentException::class);

it('is valid for a well-formed non-negative amount and invalid otherwise', function (): void {
    expect(AbsolutePriceInput::isValid('18.00'))->toBeTrue()
        ->and(AbsolutePriceInput::isValid('0.00'))->toBeTrue()
        ->and(AbsolutePriceInput::isValid('-5.00'))->toBeFalse()
        ->and(AbsolutePriceInput::isValid('a lot'))->toBeFalse()
        ->and(AbsolutePriceInput::isValid(''))->toBeFalse()
        ->and(AbsolutePriceInput::isValid(null))->toBeFalse();
});
