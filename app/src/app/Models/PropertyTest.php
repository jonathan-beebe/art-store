<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\PropertyDataType;

it('casts its data type', function (): void {
    expect(Property::factory()->create()->data_type)->toBe(PropertyDataType::Enum)
        ->and(Property::factory()->text()->create()->data_type)->toBe(PropertyDataType::Text)
        ->and(Property::factory()->number()->create()->data_type)->toBe(PropertyDataType::Number);
});
