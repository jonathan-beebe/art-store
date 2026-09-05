<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\DescriptionSectionKind;

/**
 * The seller-facing name for a listing page section's kind — an authored
 * phrase, never the schema word ("faq", "size_chart") a seller has no
 * reason to learn. One map, reused everywhere a kind is named: the
 * "Add a section" buttons and the pill on an existing section's card.
 */
final class DescriptionSectionKindWord
{
    private function __construct() {} // @codeCoverageIgnore

    public static function forKind(DescriptionSectionKind $kind): string
    {
        return match ($kind) {
            DescriptionSectionKind::Text => 'Plain text',
            DescriptionSectionKind::Specs => 'Details list',
            DescriptionSectionKind::SizeChart => 'Size chart',
            DescriptionSectionKind::Faq => 'Q & A',
            DescriptionSectionKind::Care => 'Care',
            DescriptionSectionKind::Disclaimer => 'The fine print',
        };
    }

    /**
     * The short hint under a kind's "Add a section" button — only the two
     * kinds the mock calls out get one; the rest are self-explanatory.
     */
    public static function hint(DescriptionSectionKind $kind): ?string
    {
        return match ($kind) {
            DescriptionSectionKind::Specs => 'label & value rows',
            DescriptionSectionKind::Disclaimer => 'color varies, returns…',
            default => null,
        };
    }
}
