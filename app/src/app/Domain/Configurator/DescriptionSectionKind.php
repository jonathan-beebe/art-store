<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * A typed slice of a listing's description. `Text`, `Care`, and `Disclaimer`
 * render `body_md`; `Specs`, `SizeChart`, and `Faq` render `body_json`.
 */
enum DescriptionSectionKind: string
{
    case Text = 'text';
    case Specs = 'specs';
    case SizeChart = 'size_chart';
    case Faq = 'faq';
    case Care = 'care';
    case Disclaimer = 'disclaimer';

    public function rendersFromJson(): bool
    {
        return match ($this) {
            self::Specs, self::SizeChart, self::Faq => true,
            self::Text, self::Care, self::Disclaimer => false,
        };
    }
}
