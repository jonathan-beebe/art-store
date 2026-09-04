<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('carries an option value’s id and price', function (): void {
    $snapshot = new StandaloneOptionSnapshot('ovl_01', 1800);

    expect($snapshot->id)->toBe('ovl_01')
        ->and($snapshot->priceCents)->toBe(1800);
});

it('allows a null price for an option that never got one', function (): void {
    $snapshot = new StandaloneOptionSnapshot('ovl_01', null);

    expect($snapshot->priceCents)->toBeNull();
});
