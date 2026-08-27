<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\PublishIssue;
use App\Models\Listing;
use App\Models\OptionValue;
use App\Models\Variant;
use App\Models\VariantOption;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Turns one {@see PublishIssue} the domain raised in schema words
 * (variant, axis, modifier) into the sentence and fix link the seller-facing
 * publish panel shows — the one place `PublishIssue::$code` is read outside
 * the domain's own validation. A variant-scoped issue names the combination
 * by its option labels when the variant still exists and carries options,
 * falling back to the generic phrasing otherwise.
 */
final class PublishIssuePresenter
{
    private function __construct() {} // @codeCoverageIgnore

    public static function present(PublishIssue $issue, Listing $listing): PresentedPublishIssue
    {
        return match ($issue->code) {
            'variant_priced_negative' => PresentedPublishIssue::of(
                self::negativePriceMessage($listing, $issue->subjectId),
                'Fix it in Choices & combinations',
                self::combinationUrl($listing, $issue),
            ),
            'variant_missing_axis_value' => PresentedPublishIssue::of(
                self::missingAxisValueMessage($listing, $issue->subjectId),
                'Fix it in Choices & combinations',
                self::combinationUrl($listing, $issue),
            ),
            'serialized_variant_has_no_units' => PresentedPublishIssue::of(
                self::noUnitsMessage($listing, $issue->subjectId),
                'Add pieces in Individual pieces',
                route('seller.listings.variants.units.index', [$listing, $issue->subjectId]),
            ),
            'missing_required_attribute' => PresentedPublishIssue::of(
                "Say what it's made of — buyers filter by it.",
                'Pick one under Your item',
                route('seller.listings.edit', $listing).'#attribute-'.$issue->subjectId,
            ),
            'option_missing_price' => PresentedPublishIssue::of(
                self::missingPriceMessage($listing, $issue->subjectId),
                'Fix it in Choices',
                route('seller.listings.option-axes.index', $listing).'#'.$issue->subjectId,
            ),
            'axis_too_many_options' => PresentedPublishIssue::of(
                'One of your choices holds more options than the platform allows — trim its list before this can go live.',
                'Fix it in Choices',
                route('seller.listings.option-axes.index', $listing),
            ),
            'too_many_variants' => PresentedPublishIssue::of(
                'This listing holds more combinations than the platform allows — remove some before it can go live.',
                'Fix it in Choices & combinations',
                route('seller.listings.variants.index', $listing),
            ),
            'too_many_modifiers' => PresentedPublishIssue::of(
                'This listing asks more questions than the platform allows — remove one before it can go live.',
                'Fix it in Questions',
                route('seller.listings.modifiers.index', $listing),
            ),
            'too_many_quantity_tiers' => PresentedPublishIssue::of(
                'This listing holds more quantity discounts than the platform allows — remove one before it can go live.',
                'Fix it in Quantity discounts',
                route('seller.listings.quantity-breaks.index', $listing),
            ),
            'too_many_sections' => PresentedPublishIssue::of(
                'This listing holds more page sections than the platform allows — remove one before it can go live.',
                'Fix it in Listing page sections',
                route('seller.listings.description-sections.index', $listing),
            ),
            default => throw new LogicException("No presentation is defined for publish issue code \"{$issue->code}\"."),
        };
    }

    private static function combinationUrl(Listing $listing, PublishIssue $issue): string
    {
        return route('seller.listings.variants.index', $listing).'#'.$issue->subjectId;
    }

    private static function negativePriceMessage(Listing $listing, ?string $variantId): string
    {
        $label = self::combinationLabel($listing, $variantId);

        return $label === null
            ? "One of your combinations' price comes out below zero once its price differences apply — buyers can't be charged a negative amount."
            : "The {$label} combination's price comes out below zero once its price differences apply — buyers can't be charged a negative amount.";
    }

    private static function missingAxisValueMessage(Listing $listing, ?string $variantId): string
    {
        $label = self::combinationLabel($listing, $variantId);

        return $label === null
            ? "One of your combinations doesn't carry an option for every choice — pick one for each before it can be offered."
            : "The {$label} combination doesn't carry an option for every choice — pick one for each before it can be offered.";
    }

    private static function noUnitsMessage(Listing $listing, ?string $variantId): string
    {
        $label = self::combinationLabel($listing, $variantId);

        return $label === null
            ? "You marked one of your combinations one-of-a-kind, but the piece list is empty — there's nothing to sell yet."
            : "You marked the {$label} combination one-of-a-kind, but the piece list is empty — there's nothing to sell yet.";
    }

    private static function missingPriceMessage(Listing $listing, ?string $optionValueId): string
    {
        $label = self::optionLabel($listing, $optionValueId);

        return $label === null
            ? 'One of your options has no price yet — every option in a choice priced on its own needs one before it can go live.'
            : "The {$label} option has no price yet — give it one before this can go live.";
    }

    /**
     * The label naming the option a publish issue points at — `null` when
     * the issue carries no subject or the option is gone, so the caller
     * falls back to naming it generically.
     */
    private static function optionLabel(Listing $listing, ?string $optionValueId): ?string
    {
        if ($optionValueId === null) {
            return null;
        }

        $value = OptionValue::query()
            ->whereHas('axis', fn (Builder $axes): Builder => $axes->where('listing_id', $listing->id))
            ->find($optionValueId);

        return $value?->label;
    }

    /**
     * The option labels naming the variant a publish issue points at, joined
     * the way the seller screens name a combination — `null` when the issue
     * carries no subject, the variant is gone, or it never picked up an
     * option (an axis-free listing's single combination), so the caller
     * falls back to naming it generically.
     */
    private static function combinationLabel(Listing $listing, ?string $variantId): ?string
    {
        if ($variantId === null) {
            return null;
        }

        $variant = Variant::query()->where('listing_id', $listing->id)->with('options.optionValue')->find($variantId);

        if ($variant === null) {
            return null;
        }

        $labels = $variant->options
            ->map(fn (VariantOption $option): ?string => $option->optionValue?->label)
            ->filter()
            ->values();

        return $labels->isEmpty() ? null : $labels->implode(' · ');
    }
}
