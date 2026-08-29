<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\DomainRuleViolation;
use InvalidArgumentException;

it('refuses a quantity below one', function (): void {
    ConfiguredCartQuantity::withinStock(0, true, false, null);
})->throws(InvalidArgumentException::class);

it('refuses an unavailable configuration', function (): void {
    ConfiguredCartQuantity::withinStock(1, false, false, null);
})->throws(DomainRuleViolation::class, 'That configuration is no longer available.');

it('caps a serialized variant at one unit no matter what was requested', function (): void {
    expect(ConfiguredCartQuantity::withinStock(5, true, true, null))->toBe(1);
});

it('leaves an uncapped variant quantity alone', function (): void {
    expect(ConfiguredCartQuantity::withinStock(5, true, false, null))->toBe(5);
});

it('caps a tracked variant quantity at what is left', function (): void {
    expect(ConfiguredCartQuantity::withinStock(9, true, false, 3))->toBe(3);
});
