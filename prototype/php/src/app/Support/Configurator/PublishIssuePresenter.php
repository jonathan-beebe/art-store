<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\PublishIssue;
use App\Models\Listing;
use LogicException;

/**
 * Turns one {@see PublishIssue} the domain raised in schema words
 * (variant, axis, modifier) into the sentence and fix link the seller-facing
 * publish panel shows — the one place `PublishIssue::$code` is read outside
 * the domain's own validation.
 */
final class PublishIssuePresenter
{
    private function __construct() {} // @codeCoverageIgnore

    public static function present(PublishIssue $issue, Listing $listing): PresentedPublishIssue
    {
        return match ($issue->code) {
            'variant_priced_negative' => PresentedPublishIssue::of(
                "One of your combinations' price comes out below zero once its price differences apply — buyers can't be charged a negative amount.",
                'Fix it in Choices & combinations',
                self::combinationUrl($listing, $issue),
            ),
            'variant_missing_axis_value' => PresentedPublishIssue::of(
                "One of your combinations doesn't carry an option for every choice — pick one for each before it can be offered.",
                'Fix it in Choices & combinations',
                self::combinationUrl($listing, $issue),
            ),
            'serialized_variant_has_no_units' => PresentedPublishIssue::of(
                "You marked one of your combinations one-of-a-kind, but the piece list is empty — there's nothing to sell yet.",
                'Add pieces in Individual pieces',
                route('seller.listings.variants.units.index', [$listing, $issue->subjectId]),
            ),
            'missing_required_attribute' => PresentedPublishIssue::of(
                "Say what it's made of — buyers filter by it.",
                'Pick one under Your item',
                route('seller.listings.edit', $listing).'#attribute-'.$issue->subjectId,
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
}
