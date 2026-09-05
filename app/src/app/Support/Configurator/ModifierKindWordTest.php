<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\ModifierKind;

it('gives every kind a craft phrase', function (ModifierKind $kind, string $word): void {
    expect(ModifierKindWord::forKind($kind))->toBe($word);
})->with([
    'text' => [ModifierKind::Text, 'they type it'],
    'select' => [ModifierKind::Select, 'they pick from your list'],
    'measurement' => [ModifierKind::Measurement, 'they give a measurement'],
]);
