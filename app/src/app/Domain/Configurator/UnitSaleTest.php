<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\DomainRuleViolation;

it('sells an available unit', function (): void {
    expect(UnitSale::afterSale(UnitState::Available))->toBe(UnitState::Sold);
});

it('rejects selling a unit that is not available', function (UnitState $state): void {
    expect(fn () => UnitSale::afterSale($state))
        ->toThrow(DomainRuleViolation::class, 'That piece is no longer available.');
})->with([
    'already sold' => [UnitState::Sold],
    'reserved' => [UnitState::Reserved],
]);

it('restocks a sold unit', function (): void {
    expect(UnitSale::afterRestock(UnitState::Sold))->toBe(UnitState::Available);
});

it('rejects restocking a unit that was not sold', function (UnitState $state): void {
    expect(fn () => UnitSale::afterRestock($state))
        ->toThrow(DomainRuleViolation::class, 'That piece was not sold.');
})->with([
    'already available' => [UnitState::Available],
    'reserved' => [UnitState::Reserved],
]);
