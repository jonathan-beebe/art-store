<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('gives an axis-free listing the same fingerprint every time', function (): void {
    $first = CartLineFingerprint::of(null, null, []);
    $second = CartLineFingerprint::of(null, null, []);

    expect($first->equals($second))->toBeTrue();
});

it('differs by variant, unit, or answers', function (): void {
    $base = CartLineFingerprint::of('vrt_01', null, []);

    expect($base->equals(CartLineFingerprint::of('vrt_02', null, [])))->toBeFalse()
        ->and($base->equals(CartLineFingerprint::of('vrt_01', 'unt_01', [])))->toBeFalse()
        ->and($base->equals(CartLineFingerprint::of('vrt_01', null, ['mdf_01' => 'Alice'])))->toBeFalse();
});

it('does not care what order the answers were built in', function (): void {
    $a = CartLineFingerprint::of('vrt_01', null, ['mdf_02' => 'b', 'mdf_01' => 'a']);
    $b = CartLineFingerprint::of('vrt_01', null, ['mdf_01' => 'a', 'mdf_02' => 'b']);

    expect($a->equals($b))->toBeTrue();
});
