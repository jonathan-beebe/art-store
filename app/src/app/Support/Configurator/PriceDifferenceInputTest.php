<?php

declare(strict_types=1);

namespace App\Support\Configurator;

it('formats a zero difference as an em dash', function (): void {
    expect(PriceDifferenceInput::format(0))->toBe('—');
});

it('formats a positive difference with a leading plus', function (): void {
    expect(PriceDifferenceInput::format(600))->toBe('+$6.00');
});

it('formats a negative difference with the minus Money already carries', function (): void {
    expect(PriceDifferenceInput::format(-350))->toBe('-$3.50');
});

it('parses a missing, blank, or em-dash value as zero', function (): void {
    expect(PriceDifferenceInput::parseCents(null))->toBe(0)
        ->and(PriceDifferenceInput::parseCents(''))->toBe(0)
        ->and(PriceDifferenceInput::parseCents('  '))->toBe(0)
        ->and(PriceDifferenceInput::parseCents('—'))->toBe(0);
});

it('parses every value it formats back to the same cents', function (): void {
    expect(PriceDifferenceInput::parseCents('+$6.00'))->toBe(600)
        ->and(PriceDifferenceInput::parseCents('-$3.50'))->toBe(-350)
        ->and(PriceDifferenceInput::parseCents('5'))->toBe(500)
        ->and(PriceDifferenceInput::parseCents('-2.50'))->toBe(-250);
});

it('rejects a value that is not a dollar amount', function (): void {
    expect(PriceDifferenceInput::isValid('a lot'))->toBeFalse();
});

it('accepts everything it can format', function (): void {
    expect(PriceDifferenceInput::isValid('+$6.00'))->toBeTrue();
});
