<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * Whether a listing's configurator state is ready to publish. Pure: it judges
 * the snapshot it is given, reading nothing itself — an adapter folds the
 * listing's axes, variants, modifiers, quantity breaks, and sections into
 * these primitives before asking here. Not yet wired to a controller
 * (FEAT-026 does that); this ticket lands the check and proves it against
 * the eight archetype seeds.
 */
final readonly class ConfiguratorPublishValidation
{
    public const int MAX_OPTIONS_PER_AXIS = 70;

    public const int MAX_VARIANTS = 500;

    public const int MAX_MODIFIERS = 5;

    public const int MAX_QUANTITY_TIERS = 10;

    public const int MAX_SECTIONS = 15;

    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  list<string>  $axisIds
     * @param  list<int>  $optionCountsPerAxis
     * @param  list<VariantSnapshot>  $variants
     * @param  list<string>  $requiredAttributePropertyIds  `category_properties` grants that are both `usable_as_attribute` and `required` for this listing's category
     * @param  list<string>  $attributedPropertyIds  property ids the listing already holds at least one `listing_attributes` row for
     * @param  list<StandaloneOptionSnapshot>  $standaloneOptions  every option value on one of the listing's `standalone` axes
     * @return list<PublishIssue>
     */
    public static function check(
        array $axisIds,
        array $optionCountsPerAxis,
        array $variants,
        int $modifierCount,
        int $quantityBreakCount,
        int $sectionCount,
        array $requiredAttributePropertyIds = [],
        array $attributedPropertyIds = [],
        array $standaloneOptions = [],
    ): array {
        $issues = [];

        foreach ($variants as $variant) {
            if ($variant->enabled && $variant->priceCents < 0) {
                $issues[] = PublishIssue::of('variant_priced_negative', "Variant {$variant->id} is priced below zero.", $variant->id);
            }

            if ($variant->enabled && array_diff($axisIds, $variant->axisIdsCovered) !== []) {
                $issues[] = PublishIssue::of('variant_missing_axis_value', "Variant {$variant->id} does not carry a value for every axis.", $variant->id);
            }

            if ($variant->enabled && $variant->isSerialized && $variant->availableUnitCount < 1) {
                $issues[] = PublishIssue::of('serialized_variant_has_no_units', "Serialized variant {$variant->id} has no available unit.", $variant->id);
            }
        }

        foreach ($requiredAttributePropertyIds as $propertyId) {
            if (! in_array($propertyId, $attributedPropertyIds, true)) {
                $issues[] = PublishIssue::of('missing_required_attribute', 'A required attribute has no value set.', $propertyId);
            }
        }

        foreach ($standaloneOptions as $option) {
            if ($option->priceCents === null || $option->priceCents < 0) {
                $issues[] = PublishIssue::of('option_missing_price', "Option {$option->id} has no price.", $option->id);
            }
        }

        foreach ($optionCountsPerAxis as $count) {
            if ($count > self::MAX_OPTIONS_PER_AXIS) {
                $issues[] = PublishIssue::of('axis_too_many_options', 'An axis holds more than '.self::MAX_OPTIONS_PER_AXIS.' options.');

                break;
            }
        }

        if (count($variants) > self::MAX_VARIANTS) {
            $issues[] = PublishIssue::of('too_many_variants', 'The listing holds more than '.self::MAX_VARIANTS.' variants.');
        }

        if ($modifierCount > self::MAX_MODIFIERS) {
            $issues[] = PublishIssue::of('too_many_modifiers', 'The listing asks more than '.self::MAX_MODIFIERS.' questions.');
        }

        if ($quantityBreakCount > self::MAX_QUANTITY_TIERS) {
            $issues[] = PublishIssue::of('too_many_quantity_tiers', 'The listing holds more than '.self::MAX_QUANTITY_TIERS.' quantity tiers.');
        }

        if ($sectionCount > self::MAX_SECTIONS) {
            $issues[] = PublishIssue::of('too_many_sections', 'The listing holds more than '.self::MAX_SECTIONS.' description sections.');
        }

        return $issues;
    }
}
