<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Configurator\PricingMode;
use App\Domain\Listings\ListingCreationChoice;
use App\Domain\Listings\ListingDraft;
use App\Domain\Money\Money;
use App\Models\Variant;

$draft = fn (int $priceCents = 4600): ListingDraft => ListingDraft::of('Maple Serving Board', null, null, Money::fromCents($priceCents), 12);

it('creates a plain listing when the shape adds no choice', function () use ($draft): void {
    $listing = app(CreateListingWithChoice::class)($this->seller(), $draft(), null);

    expect($listing->optionAxes()->count())->toBe(0)
        ->and($listing->price_cents)->toBe(4600);
});

it('adds a standalone choice, one priced option per version, one variant each, and syncs the price', function () use ($draft): void {
    $choice = ListingCreationChoice::versions('Size', [
        ['label' => '8x10', 'cents' => 1800],
        ['label' => '11x14', 'cents' => 2400],
    ]);

    $listing = app(CreateListingWithChoice::class)($this->seller(), $draft(0), $choice);

    $axis = $listing->optionAxes()->sole();
    expect($axis->pricing_mode)->toBe(PricingMode::Standalone)
        ->and($axis->optionValues()->orderBy('position')->pluck('price_cents')->all())->toBe([1800, 2400])
        ->and($axis->optionValues()->where('is_default', true)->sole()->label)->toBe('8x10')
        ->and(Variant::where('listing_id', $listing->id)->count())->toBe(2)
        ->and($listing->refresh()->price_cents)->toBe(1800);
});

it('adds an add-on choice with surcharged options and a variant per option', function () use ($draft): void {
    $choice = ListingCreationChoice::extras('Finish', [
        ['label' => 'Oil finish', 'cents' => 0],
        ['label' => 'Carved handle', 'cents' => 1400],
    ]);

    $listing = app(CreateListingWithChoice::class)($this->seller(), $draft(), $choice);

    $axis = $listing->optionAxes()->sole();
    expect($axis->pricing_mode)->toBe(PricingMode::AddOn)
        ->and($axis->optionValues()->orderBy('position')->pluck('surcharge_cents')->all())->toBe([0, 1400])
        ->and($axis->optionValues()->whereNotNull('price_cents')->count())->toBe(0)
        ->and(Variant::where('listing_id', $listing->id)->count())->toBe(2)
        ->and($listing->refresh()->price_cents)->toBe(4600);
});
