<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('sorts option value ids so order of choice does not matter', function (): void {
    $a = ComboKey::of(['ovl_02', 'ovl_01']);
    $b = ComboKey::of(['ovl_01', 'ovl_02']);

    expect($a->value)->toBe('ovl_01/ovl_02')
        ->and($a->equals($b))->toBeTrue();
});

it('gives an axis-free listing the empty key', function (): void {
    expect(ComboKey::of([])->value)->toBe('');
});

it('is not equal when the combination differs', function (): void {
    expect(ComboKey::of(['ovl_01'])->equals(ComboKey::of(['ovl_02'])))->toBeFalse();
});
