<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\DescriptionSectionKind;

it('gives every kind an authored name', function (DescriptionSectionKind $kind, string $word): void {
    expect(DescriptionSectionKindWord::forKind($kind))->toBe($word);
})->with([
    'text' => [DescriptionSectionKind::Text, 'Plain text'],
    'specs' => [DescriptionSectionKind::Specs, 'Details list'],
    'size_chart' => [DescriptionSectionKind::SizeChart, 'Size chart'],
    'faq' => [DescriptionSectionKind::Faq, 'Q & A'],
    'care' => [DescriptionSectionKind::Care, 'Care'],
    'disclaimer' => [DescriptionSectionKind::Disclaimer, 'The fine print'],
]);

it('never renders the raw schema word', function (DescriptionSectionKind $kind): void {
    expect(DescriptionSectionKindWord::forKind($kind))->not->toBe($kind->value);
})->with(DescriptionSectionKind::cases());

it('hints only the two kinds the mock calls out', function (): void {
    expect(DescriptionSectionKindWord::hint(DescriptionSectionKind::Specs))->toBe('label & value rows')
        ->and(DescriptionSectionKindWord::hint(DescriptionSectionKind::Disclaimer))->toBe('color varies, returns…')
        ->and(DescriptionSectionKindWord::hint(DescriptionSectionKind::Text))->toBeNull()
        ->and(DescriptionSectionKindWord::hint(DescriptionSectionKind::SizeChart))->toBeNull()
        ->and(DescriptionSectionKindWord::hint(DescriptionSectionKind::Faq))->toBeNull()
        ->and(DescriptionSectionKindWord::hint(DescriptionSectionKind::Care))->toBeNull();
});
