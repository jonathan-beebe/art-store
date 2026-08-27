<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('renders specs, size charts, and faqs from json, the rest from markdown', function (): void {
    expect(DescriptionSectionKind::Specs->rendersFromJson())->toBeTrue()
        ->and(DescriptionSectionKind::SizeChart->rendersFromJson())->toBeTrue()
        ->and(DescriptionSectionKind::Faq->rendersFromJson())->toBeTrue()
        ->and(DescriptionSectionKind::Text->rendersFromJson())->toBeFalse()
        ->and(DescriptionSectionKind::Care->rendersFromJson())->toBeFalse()
        ->and(DescriptionSectionKind::Disclaimer->rendersFromJson())->toBeFalse();
});
