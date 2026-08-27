<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\PublishIssue;
use LogicException;

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
