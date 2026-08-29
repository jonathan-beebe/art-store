<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\DescriptionSectionKind;
use App\Models\Listing;

/**
 * The view data every listing-page-sections render needs, from the plain
 * index page to the re-render a rate-limited save falls back to — one place
 * so every route that lands on this screen builds the same shape.
 */
final class DescriptionSectionIndexPageData
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return array<string, mixed>
     */
    public static function build(Listing $listing, ?string $rawAddKind = null): array
    {
        return [
            'listing' => $listing,
            'sections' => $listing->descriptionSections()->orderBy('position')->get(),
            'addKind' => $rawAddKind === null ? null : DescriptionSectionKind::tryFrom($rawAddKind),
        ];
    }
}
