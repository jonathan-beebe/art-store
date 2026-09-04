<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\UnitState;

it('renders every unit state as a plain-language word', function (): void {
    expect(UnitStateWord::forState(UnitState::Available))->toBe('available')
        ->and(UnitStateWord::forState(UnitState::Sold))->toBe('sold')
        ->and(UnitStateWord::forState(UnitState::Reserved))->toBe('on hold');
});
