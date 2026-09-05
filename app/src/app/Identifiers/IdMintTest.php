<?php

declare(strict_types=1);

namespace App\Identifiers;

use App\Domain\DomainRuleViolation;
use App\Domain\Identifiers\PrefixedId;
use Illuminate\Support\Facades\Date;

it('mints an id of the shape every prefixed id holds', function (string $prefix): void {
    $id = IdMint::of($prefix);

    expect(PrefixedId::parse($prefix, $id))->not->toBeNull()
        ->and(strlen($id))->toBe(PrefixedId::LENGTH);
})->with(['ses', 'txn', 'req']);

it('mints a different id every time', function (): void {
    expect(IdMint::of('txn'))->not->toBe(IdMint::of('txn'));
});

it('draws the time half from the application clock', function (): void {
    Date::setTestNow('2026-08-23 18:00:00');

    $minted = IdMint::of('ses');

    Date::setTestNow('2020-01-01 00:00:00');

    expect(IdMint::of('ses') < $minted)->toBeTrue();

    Date::setTestNow();
});

it('refuses a prefix that is not three lowercase letters', function (): void {
    expect(fn () => IdMint::of('SESSION'))->toThrow(DomainRuleViolation::class);
});
