<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Configurator\ModifierKind;
use App\Domain\Money\Money;
use App\Models\Listing;
use App\Models\Modifier;
use App\Models\Seller;
use App\Models\Variant;
use LogicException;

beforeEach(function (): void {
    $this->seed(TaxonomySeeder::class);
    $this->seed(ConfiguratorArchetypeSeeder::class);
});

function archetypeListing(string $title): Listing
{
    return Listing::where('title', $title)->sole();
}

it('seeds one demo seller owning all eight archetypes', function (): void {
    $seller = Seller::where('email', ConfiguratorArchetypeSeeder::EMAIL)->sole();

    expect(Listing::where('seller_id', $seller->id)->count())->toBe(8);
});

it('gives the plain print the legacy, axis-free path with zero configurator rows', function (): void {
    $listing = archetypeListing('Meadow at Dawn, 8x10 Print');

    expect($listing->optionAxes()->count())->toBe(0)
        ->and($listing->variants()->count())->toBe(0)
        ->and($listing->modifiers()->count())->toBe(0)
        ->and($listing->isPurchasable())->toBeTrue();
});

it('generates the ring’s full 3x3 grid and prices a variant off its surcharges', function (): void {
    $listing = archetypeListing('Engraved Signet Ring');

    expect($listing->optionAxes()->count())->toBe(2)
        ->and($listing->variants()->count())->toBe(9);

    $roseGoldBothSides = $listing->variants()
        ->whereHas('options.optionValue', fn ($q) => $q->where('label', 'Rose Gold'))
        ->get()
        ->first(fn (Variant $v) => $v->options()->whereHas('optionValue', fn ($q) => $q->where('label', 'Both Sides'))->exists())
        ?? throw new LogicException('Expected a Rose Gold, Both Sides variant.');

    expect($roseGoldBothSides->resolvedPrice(Money::fromCents(12000))->cents)->toBe(12000 + 800 + 850);
});

it('scopes the ring’s font and text modifiers away from "No Engraving"', function (): void {
    $listing = archetypeListing('Engraved Signet Ring');
    $noEngraving = $listing->optionAxes()->where('name', 'Engraving')->sole()->optionValues()->where('label', 'No Engraving')->sole();
    $outside = $listing->optionAxes()->where('name', 'Engraving')->sole()->optionValues()->where('label', 'Outside Only')->sole();

    /** @var Modifier $font */
    $font = $listing->modifiers()->where('kind', ModifierKind::Select)->sole();
    /** @var Modifier $text */
    $text = $listing->modifiers()->where('kind', ModifierKind::Text)->sole();

    expect($font->appliesTo([$noEngraving->id]))->toBeFalse()
        ->and($font->appliesTo([$outside->id]))->toBeTrue()
        ->and($text->appliesTo([$noEngraving->id]))->toBeFalse()
        ->and($text->appliesTo([$outside->id]))->toBeTrue();
});

it('scopes the mug’s personalization text to only the personalized option value', function (): void {
    $listing = archetypeListing('Stoneware Coffee Mug');
    $axis = $listing->optionAxes()->sole();
    $blank = $axis->optionValues()->where('label', 'Blank')->sole();
    $personalized = $axis->optionValues()->where('label', 'Personalized')->sole();

    $text = $listing->modifiers()->sole();

    expect($text->appliesTo([$blank->id]))->toBeFalse()
        ->and($text->appliesTo([$personalized->id]))->toBeTrue();
});

it('gives the tee’s larger sizes their surcharge', function (): void {
    $listing = archetypeListing('Line Art Cat Tee');
    $size = $listing->optionAxes()->where('name', 'Size')->sole();

    expect($size->optionValues()->where('label', 'M')->sole()->surcharge_cents)->toBe(0)
        ->and($size->optionValues()->where('label', 'XL')->sole()->surcharge_cents)->toBe(150)
        ->and($size->optionValues()->where('label', 'XXL')->sole()->surcharge_cents)->toBe(300)
        ->and($listing->variants()->count())->toBe(15);
});

it('gives the walnut table sparse variants with price overrides, not the full grid', function (): void {
    $listing = archetypeListing('Live-Edge Walnut Dining Table');

    expect($listing->variants()->count())->toBe(4);

    foreach ($listing->variants()->get() as $variant) {
        expect($variant->price_override_cents)->not->toBeNull();
    }

    $attributes = $listing->listingAttributes()->with(['property', 'propertyValue'])->get();

    expect($attributes->where('property.name', 'Material')->pluck('propertyValue.label')->all())
        ->toEqualCanonicalizing(['Walnut', 'Oak'])
        ->and($attributes->where('property.name', 'Medium')->sole()->propertyValue->label)->toBe('Walnut');
});

it('derives the candlestick variant’s available quantity from its twelve units', function (): void {
    $listing = archetypeListing('Vintage Brass Candlesticks, Individually Listed');
    $variant = $listing->variants()->sole();

    expect($variant->is_serialized)->toBeTrue()
        ->and($variant->quantity)->toBeNull()
        ->and($variant->units()->count())->toBe(12)
        ->and($variant->availableUnitCount())->toBe(12)
        ->and($variant->availability()->available)->toBeTrue();
});

it('prices the invitation’s paper stock per option and offers quantity breaks', function (): void {
    $listing = archetypeListing('Letterpress Wedding Invitations');
    $paperStock = $listing->modifiers()->sole();

    expect($paperStock->options()->where('label', 'Cotton Linen')->sole()->add_on_price_cents)->toBe(100)
        ->and($listing->quantityBreaks()->count())->toBe(3)
        ->and($listing->quantityBreaks()->where('min_qty', 200)->sole()->discount_bps)->toBe(1500);
});

it('generates the pet portrait’s three-axis grid, pets and pose split from the compound-option hack', function (): void {
    $listing = archetypeListing('Custom Pet Portrait');

    expect($listing->optionAxes()->count())->toBe(3)
        ->and($listing->optionAxes()->pluck('name')->all())->toEqualCanonicalizing(['Pets', 'Pose', 'Size & Framing'])
        ->and($listing->variants()->count())->toBe(8);

    $pets = $listing->optionAxes()->where('name', 'Pets')->sole();
    $twoPets = $pets->optionValues()->where('label', '2 Pets')->sole();

    expect($twoPets->surcharge_cents)->toBe(1500);
});

it('carries the pet portraits required Medium attribute', function (): void {
    $listing = archetypeListing('Custom Pet Portrait');

    expect($listing->publishIssues())->toBe([])
        ->and($listing->listingAttributes()->with('propertyValue')->sole()->propertyValue->label)->toBe('Watercolor');
});

it('carries a Medium attribute matching every archetype’s legacy medium string', function (): void {
    foreach ([
        'Meadow at Dawn, 8x10 Print' => 'Print',
        'Engraved Signet Ring' => 'Metal',
        'Stoneware Coffee Mug' => 'Ceramic',
        'Line Art Cat Tee' => 'Apparel',
        'Live-Edge Walnut Dining Table' => 'Walnut',
        'Vintage Brass Candlesticks, Individually Listed' => 'Brass',
        'Letterpress Wedding Invitations' => 'Paper',
        'Custom Pet Portrait' => 'Watercolor',
    ] as $title => $expectedLabel) {
        $medium = archetypeListing($title)->listingAttributes()
            ->with(['property', 'propertyValue'])
            ->get()
            ->firstWhere('property.name', 'Medium');

        expect($medium?->propertyValue->label)->toBe($expectedLabel);
    }
});

it('categorizes every configured archetype', function (): void {
    foreach ([
        'Meadow at Dawn, 8x10 Print',
        'Engraved Signet Ring',
        'Stoneware Coffee Mug',
        'Line Art Cat Tee',
        'Live-Edge Walnut Dining Table',
        'Vintage Brass Candlesticks, Individually Listed',
        'Letterpress Wedding Invitations',
        'Custom Pet Portrait',
    ] as $title) {
        expect(archetypeListing($title)->category_id)->not->toBeNull();
    }
});

it('changes nothing on a second run', function (): void {
    $this->seed(ConfiguratorArchetypeSeeder::class);

    expect(Seller::where('email', ConfiguratorArchetypeSeeder::EMAIL)->count())->toBe(1)
        ->and(Listing::where('seller_id', Seller::where('email', ConfiguratorArchetypeSeeder::EMAIL)->sole()->id)->count())->toBe(8);
});
