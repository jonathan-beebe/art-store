<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\PublishIssue;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;
use App\Models\VariantOption;
use LogicException;

it('names a negative-priced combination by its option labels when the variant still exists', function (): void {
    $listing = $this->listing($this->seller());
    $size = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    $large = OptionValue::factory()->create(['axis_id' => $size->id, 'label' => 'Large']);
    $pattern = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Pattern']);
    $speckle = OptionValue::factory()->create(['axis_id' => $pattern->id, 'label' => 'Speckle']);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $size->id, 'option_value_id' => $large->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $pattern->id, 'option_value_id' => $speckle->id]);

    $presented = PublishIssuePresenter::present(PublishIssue::of('variant_priced_negative', 'irrelevant', $variant->id), $listing);

    expect($presented->message)->toBe("The Large · Speckle combination's price comes out below zero once its price differences apply — buyers can't be charged a negative amount.");
});

it('names a combination missing an axis value by its option labels when the variant still exists', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Small']);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);

    $presented = PublishIssuePresenter::present(PublishIssue::of('variant_missing_axis_value', 'irrelevant', $variant->id), $listing);

    expect($presented->message)->toBe("The Small combination doesn't carry an option for every choice — pick one for each before it can be offered.");
});

it('names a missing-piece-list combination by its option labels when the variant still exists', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Edition']);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Numbered']);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);

    $presented = PublishIssuePresenter::present(PublishIssue::of('serialized_variant_has_no_units', 'irrelevant', $variant->id), $listing);

    expect($presented->message)->toBe("You marked the Numbered combination one-of-a-kind, but the piece list is empty — there's nothing to sell yet.");
});

it('falls back to the generic phrasing for a variant that carries no options', function (): void {
    $listing = $this->listing($this->seller());
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);

    $presented = PublishIssuePresenter::present(PublishIssue::of('variant_missing_axis_value', 'irrelevant', $variant->id), $listing);

    expect($presented->message)->toBe("One of your combinations doesn't carry an option for every choice — pick one for each before it can be offered.");
});

it('falls back to the generic phrasing for a variant-scoped issue with no subject', function (): void {
    $listing = $this->listing($this->seller());

    $presented = PublishIssuePresenter::present(PublishIssue::of('variant_priced_negative', 'irrelevant'), $listing);

    expect($presented->message)->toContain('One of your combinations')
        ->and($presented->message)->toContain('price comes out below zero');
});

it('translates a negative-priced combination to a plain sentence linking the combinations screen', function (): void {
    $listing = $this->listing($this->seller());
    $issue = PublishIssue::of('variant_priced_negative', 'irrelevant domain message', 'vrt_1');

    $presented = PublishIssuePresenter::present($issue, $listing);

    expect($presented->message)->toContain("price comes out below zero once its price differences apply — buyers can't be charged a negative amount.")
        ->and($presented->fixUrl)->toBe(route('seller.listings.variants.index', $listing).'#vrt_1');
});

it('translates a combination missing an axis value to a plain sentence linking the combinations screen', function (): void {
    $listing = $this->listing($this->seller());
    $issue = PublishIssue::of('variant_missing_axis_value', 'irrelevant domain message', 'vrt_2');

    $presented = PublishIssuePresenter::present($issue, $listing);

    expect($presented->message)->toContain('pick one for each before it can be offered')
        ->and($presented->fixUrl)->toBe(route('seller.listings.variants.index', $listing).'#vrt_2');
});

it('translates an empty serialized piece list to a plain sentence linking that combinations units screen', function (): void {
    $listing = $this->listing($this->seller());
    $issue = PublishIssue::of('serialized_variant_has_no_units', 'irrelevant domain message', 'vrt_3');

    $presented = PublishIssuePresenter::present($issue, $listing);

    expect($presented->message)->toContain("the piece list is empty — there's nothing to sell yet.")
        ->and($presented->fixUrl)->toBe(route('seller.listings.variants.units.index', [$listing, 'vrt_3']));
});

it('translates a missing required attribute to the exact copy linking the facts block', function (): void {
    $listing = $this->listing($this->seller());
    $issue = PublishIssue::of('missing_required_attribute', 'irrelevant domain message', 'prp_1');

    $presented = PublishIssuePresenter::present($issue, $listing);

    expect($presented->message)->toBe("Say what it's made of — buyers filter by it.")
        ->and($presented->fixUrl)->toBe(route('seller.listings.edit', $listing).'#attribute-prp_1');
});

it('names a standalone option with no price by its label', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => '11x14', 'price_cents' => null]);

    $presented = PublishIssuePresenter::present(PublishIssue::of('option_missing_price', 'irrelevant', $value->id), $listing);

    expect($presented->message)->toBe('The 11x14 option has no price yet — give it one before this can go live.')
        ->and($presented->fixUrl)->toBe(route('seller.listings.option-axes.index', $listing).'#'.$value->id);
});

it('falls back to the generic phrasing for a missing-price issue with no subject', function (): void {
    $listing = $this->listing($this->seller());

    $presented = PublishIssuePresenter::present(PublishIssue::of('option_missing_price', 'irrelevant'), $listing);

    expect($presented->message)->toBe('One of your options has no price yet — every option in a choice priced on its own needs one before it can go live.');
});

it('falls back to the generic phrasing for a missing-price option that is gone', function (): void {
    $listing = $this->listing($this->seller());

    $presented = PublishIssuePresenter::present(PublishIssue::of('option_missing_price', 'irrelevant', 'ovl_gone'), $listing);

    expect($presented->message)->toBe('One of your options has no price yet — every option in a choice priced on its own needs one before it can go live.');
});

it('translates an oversized choice to a plain sentence linking the choices screen', function (): void {
    $listing = $this->listing($this->seller());
    $issue = PublishIssue::of('axis_too_many_options', 'irrelevant domain message');

    $presented = PublishIssuePresenter::present($issue, $listing);

    expect($presented->message)->toContain('trim its list before this can go live')
        ->and($presented->fixUrl)->toBe(route('seller.listings.option-axes.index', $listing));
});

it('translates too many combinations to a plain sentence linking the combinations screen', function (): void {
    $listing = $this->listing($this->seller());
    $issue = PublishIssue::of('too_many_variants', 'irrelevant domain message');

    $presented = PublishIssuePresenter::present($issue, $listing);

    expect($presented->message)->toContain('holds more combinations than the platform allows')
        ->and($presented->fixUrl)->toBe(route('seller.listings.variants.index', $listing));
});

it('translates too many questions to a plain sentence linking the questions screen', function (): void {
    $listing = $this->listing($this->seller());
    $issue = PublishIssue::of('too_many_modifiers', 'irrelevant domain message');

    $presented = PublishIssuePresenter::present($issue, $listing);

    expect($presented->message)->toContain('asks more questions than the platform allows')
        ->and($presented->fixUrl)->toBe(route('seller.listings.modifiers.index', $listing));
});

it('translates too many quantity tiers to a plain sentence linking the discounts screen', function (): void {
    $listing = $this->listing($this->seller());
    $issue = PublishIssue::of('too_many_quantity_tiers', 'irrelevant domain message');

    $presented = PublishIssuePresenter::present($issue, $listing);

    expect($presented->message)->toContain('holds more quantity discounts than the platform allows')
        ->and($presented->fixUrl)->toBe(route('seller.listings.quantity-breaks.index', $listing));
});

it('translates too many sections to a plain sentence linking the page sections screen', function (): void {
    $listing = $this->listing($this->seller());
    $issue = PublishIssue::of('too_many_sections', 'irrelevant domain message');

    $presented = PublishIssuePresenter::present($issue, $listing);

    expect($presented->message)->toContain('holds more page sections than the platform allows')
        ->and($presented->fixUrl)->toBe(route('seller.listings.description-sections.index', $listing));
});

it('refuses to present a code it does not recognize', function (): void {
    $listing = $this->listing($this->seller());
    $issue = PublishIssue::of('some_future_code', 'irrelevant domain message');

    PublishIssuePresenter::present($issue, $listing);
})->throws(LogicException::class);
