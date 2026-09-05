<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Configurator\ModifierKind;
use App\Models\Listing;

/**
 * The view data every questions-screen render needs, from the plain index
 * page to the re-render a rate limit or a failed option/scope save falls
 * back to — one place so all four routes that land on this screen build the
 * same shape.
 */
final class ModifierIndexPageData
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return array<string, mixed>
     */
    public static function build(Listing $listing, ?string $rawAddKind = null): array
    {
        $modifiers = $listing->modifiers()->with(['options', 'scopes.optionValue.axis.optionValues'])->orderBy('position')->get();

        return [
            'listing' => $listing,
            'modifiers' => $modifiers,
            'axes' => $listing->optionAxes()->with('optionValues')->orderBy('position')->get(),
            'addKind' => $rawAddKind === null ? null : ModifierKind::tryFrom($rawAddKind),
            'preview' => ScopedListingPreview::resolve($modifiers),
        ];
    }
}
