<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('says only available is available', function (): void {
    expect(UnitState::Available->isAvailable())->toBeTrue()
        ->and(UnitState::Reserved->isAvailable())->toBeFalse()
        ->and(UnitState::Sold->isAvailable())->toBeFalse();
});
