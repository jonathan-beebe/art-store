<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('says only enum enumerates values', function (): void {
    expect(PropertyDataType::Enum->enumeratesValues())->toBeTrue()
        ->and(PropertyDataType::Text->enumeratesValues())->toBeFalse()
        ->and(PropertyDataType::Number->enumeratesValues())->toBeFalse();
});
