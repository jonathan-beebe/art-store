<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\DomainRuleViolation;
use InvalidArgumentException;

it('decrements a tracked quantity by what sold', function (): void {
    expect(VariantStock::afterSale(3, 1))->toBe(2);
});

it('leaves an untracked quantity null through a sale', function (): void {
    expect(VariantStock::afterSale(null, 5))->toBeNull();
});

it('rejects a sale for more than a tracked quantity holds', function (): void {
    expect(fn () => VariantStock::afterSale(1, 2))
        ->toThrow(DomainRuleViolation::class, 'That configuration has only 1 left.');
});

it('rejects a sale quantity below one', function (): void {
    expect(fn () => VariantStock::afterSale(3, 0))->toThrow(InvalidArgumentException::class);
});

it('restores a tracked quantity by what came back', function (): void {
    expect(VariantStock::afterRestock(1, 2))->toBe(3);
});

it('leaves an untracked quantity null through a restock', function (): void {
    expect(VariantStock::afterRestock(null, 2))->toBeNull();
});

it('rejects a restock quantity below one', function (): void {
    expect(fn () => VariantStock::afterRestock(1, 0))->toThrow(InvalidArgumentException::class);
});
