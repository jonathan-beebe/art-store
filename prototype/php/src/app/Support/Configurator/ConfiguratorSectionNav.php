<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\PublishIssue;

/**
 * The configurator's left-rail section list the focused layout renders on
 * every listing-editing screen — one entry per section page, in the order
 * the hub lists them. Each carries the route its link resolves against, the
 * pattern {@see \Illuminate\Http\Request::routeIs()} matches to mark it the
 * current section, and the publish-issue codes that name it — the only way
 * a section earns the rail's red dot. A section with no reachable issue
 * code (Photos, FAQs) never carries one; this deliberately stops short of an
 * invented "done" checkmark no data backs.
 */
final class ConfiguratorSectionNav
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return list<array{label: string, route: string, pattern: string, issueCodes: list<string>}>
     */
    public static function sections(): array
    {
        return [
            ['label' => 'Basics', 'route' => 'seller.listings.basics.edit', 'pattern' => 'seller.listings.basics.edit', 'issueCodes' => ['missing_required_attribute']],
            ['label' => 'Photos', 'route' => 'seller.listings.images.index', 'pattern' => 'seller.listings.images.*', 'issueCodes' => []],
            ['label' => 'Pricing & options', 'route' => 'seller.listings.option-axes.index', 'pattern' => 'seller.listings.option-axes.*', 'issueCodes' => ['option_missing_price', 'axis_too_many_options']],
            // Labeled "Combinations", never "Variants" — the seller-facing
            // screens under this route never say the schema word (the units
            // screen has its own test asserting exactly that).
            ['label' => 'Combinations', 'route' => 'seller.listings.variants.index', 'pattern' => 'seller.listings.variants.*', 'issueCodes' => ['variant_priced_negative', 'variant_missing_axis_value', 'too_many_variants', 'serialized_variant_has_no_units']],
            ['label' => 'Questions', 'route' => 'seller.listings.modifiers.index', 'pattern' => 'seller.listings.modifiers.*', 'issueCodes' => ['too_many_modifiers']],
            ['label' => 'Quantity discounts', 'route' => 'seller.listings.quantity-breaks.index', 'pattern' => 'seller.listings.quantity-breaks.*', 'issueCodes' => ['too_many_quantity_tiers']],
            ['label' => 'Description sections', 'route' => 'seller.listings.description-sections.index', 'pattern' => 'seller.listings.description-sections.*', 'issueCodes' => ['too_many_sections']],
            ['label' => 'FAQs', 'route' => 'seller.listings.faqs.index', 'pattern' => 'seller.listings.faqs.*', 'issueCodes' => []],
        ];
    }

    /**
     * Whether any issue in the given list names this section — the rail's
     * only per-section state signal.
     *
     * @param  list<PublishIssue>  $issues
     * @param  list<string>  $issueCodes
     */
    public static function hasIssue(array $issues, array $issueCodes): bool
    {
        if ($issueCodes === []) {
            return false;
        }

        foreach ($issues as $issue) {
            if (in_array($issue->code, $issueCodes, true)) {
                return true;
            }
        }

        return false;
    }
}
