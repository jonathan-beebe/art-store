<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('joins the selected option labels with a slash', function (): void {
    expect(OverridePriceLabel::forCombination(['48 in', '30 in']))->toBe('48 in / 30 in');
});

it('names a single-axis combination on its own', function (): void {
    expect(OverridePriceLabel::forCombination(['Rose Gold']))->toBe('Rose Gold');
});

it('falls back to "Base price" with no axis to name', function (): void {
    expect(OverridePriceLabel::forCombination([]))->toBe('Base price');
});
