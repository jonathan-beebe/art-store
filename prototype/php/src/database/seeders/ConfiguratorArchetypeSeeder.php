<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Configurator\AddDescriptionSection;
use App\Actions\Configurator\AddModifierOption;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\AddQuantityBreak;
use App\Actions\Configurator\AddUnit;
use App\Actions\Configurator\CreateModifier;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\CreateVariant;
use App\Actions\Configurator\GenerateVariants;
use App\Actions\Configurator\ScopeModifier;
use App\Actions\Listings\CreateListing;
use App\Domain\Configurator\DescriptionSectionKind;
use App\Domain\Configurator\ModifierKind;
use App\Domain\Configurator\PricingMode;
use App\Domain\Listings\ListingDraft;
use App\Domain\Listings\ListingStatus;
use App\Domain\Money\Money;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Property;
use App\Models\PropertyValue;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Database\Seeder;

/**
 * The eight configurator archetypes from `__local__/item-configuration/etsy-product-configuration.md`
 * §2.1–2.2, each built through the real seller-facing actions rather than raw
 * `Model::create`, so this seeder proves the schema and the pricing math
 * against the hard cases the design doc set out to fix — not just a unit
 * test's happy path. Depends on {@see TaxonomySeeder} having already run.
 */
class ConfiguratorArchetypeSeeder extends Seeder
{
    public const EMAIL = 'george@example.com';

    public function run(): void
    {
        if (Seller::where('email', self::EMAIL)->exists()) {
            return;
        }

        $seller = Seller::create([
            'email' => self::EMAIL,
            'name' => 'George Weasley',
            'shop_name' => "Weasleys' Wizard Wheezes",
            'email_verified_at' => new DateTimeImmutable('2026-08-20 00:00:00'),
        ]);

        $this->plainPrint($seller);
        $this->engravedRing($seller);
        $this->personalizedMug($seller);
        $this->podTee($seller);
        $this->walnutTable($seller);
        $this->vintageCandlesticks($seller);
        $this->weddingInvitations($seller);
        $this->petPortrait($seller);
        $this->sunsetRidgePrint($seller);
    }

    /**
     * Zero axes: the legacy path every other listing already takes. No
     * configurator rows follow this one at all.
     */
    private function plainPrint(Seller $seller): Listing
    {
        $listing = $this->createListing(
            $seller,
            'Quidditch Pitch at Dawn, 8x10 Print',
            'A giclée print of the Quidditch pitch behind the shop, shot at first light on a spring morning.',
            '8 x 10 in',
            3500,
            10,
            category: Category::where('name', 'Art')->sole(),
        );

        $this->attribute($listing, 'Medium', 'Print');

        return $listing;
    }

    /**
     * Two axes (metal, engraving) with surcharges, a font select and a text
     * modifier both scoped away from "No Engraving" — the option value they
     * do not apply to.
     */
    private function engravedRing(Seller $seller): Listing
    {
        $listing = $this->createListing(
            $seller,
            'Engraved House Signet Ring',
            'A solid signet ring, hand-finished and ready for an inside or outside engraving.',
            'Ring sizes 6-9',
            12000,
            5,
            category: Category::where('name', 'Rings')->sole(),
        );

        $metal = $this->axis($listing, 'Metal');
        $this->value($metal, 'Gold', 0, isDefault: true);
        $this->value($metal, 'Silver', 0);
        $this->value($metal, 'Rose Gold', 800);

        // "No Engraving" is deliberately left out of both scopes below: a
        // buyer who picks it never sees a font or a text box to fill in.
        $engraving = $this->axis($listing, 'Engraving');
        $this->value($engraving, 'No Engraving', 0, isDefault: true);
        $outside = $this->value($engraving, 'Outside Only', 500);
        $both = $this->value($engraving, 'Both Sides', 850);

        app(GenerateVariants::class)($listing);

        $font = app(CreateModifier::class)($listing, ModifierKind::Select, 'Engraving Font', required: true, position: 0);
        app(AddModifierOption::class)($font, 'Block', 0, 0);
        app(AddModifierOption::class)($font, 'Script', 0, 1);
        app(ScopeModifier::class)($font, [$outside, $both]);

        $text = app(CreateModifier::class)($listing, ModifierKind::Text, 'Engraving Text', instructions: 'Up to 20 characters.', required: true, position: 1, charLimit: 20);
        app(ScopeModifier::class)($text, [$outside, $both]);

        $this->attribute($listing, 'Medium', 'Metal');

        return $listing;
    }

    /**
     * The "BLANK / EMPTY MUG" hack, fixed: an honest product-type axis with a
     * surcharge, and a personalization text box scoped only to the
     * personalized option value.
     */
    private function personalizedMug(Seller $seller): Listing
    {
        $listing = $this->createListing(
            $seller,
            'Three Broomsticks Stoneware Mug',
            'A 12oz stoneware mug, glazed in-house, with an optional personalized name or word.',
            '12 oz',
            1800,
            20,
            category: Category::where('name', 'Home Goods')->sole(),
        );

        $personalization = $this->axis($listing, 'Personalization');
        $this->value($personalization, 'Blank', 0, isDefault: true);
        $personalized = $this->value($personalization, 'Personalized', 300);

        app(GenerateVariants::class)($listing);

        $text = app(CreateModifier::class)($listing, ModifierKind::Text, 'Personalization Text', instructions: 'One name or short word.', required: true, charLimit: 16);
        app(ScopeModifier::class)($text, [$personalized]);

        $this->attribute($listing, 'Medium', 'Ceramic');

        return $listing;
    }

    /**
     * Color × size, with size-tier surcharges standing in for Etsy's fake
     * variation-as-quantity-tier — an honest option value instead.
     */
    private function podTee(Seller $seller): Listing
    {
        $listing = $this->createListing(
            $seller,
            'Line Art Kneazle Tee',
            'A single-line kneazle drawing, screen printed on soft ringspun cotton.',
            'Unisex fit',
            2200,
            50,
            category: Category::where('name', 'Apparel')->sole(),
        );

        $color = $this->axis($listing, 'Color');
        $this->value($color, 'Black', 0, isDefault: true);
        $this->value($color, 'White', 0);
        $this->value($color, 'Heather Grey', 0);

        $size = $this->axis($listing, 'Size');
        $this->value($size, 'S', 0);
        $this->value($size, 'M', 0, isDefault: true);
        $this->value($size, 'L', 0);
        $this->value($size, 'XL', 150);
        $this->value($size, 'XXL', 300);

        app(GenerateVariants::class)($listing);

        $this->attribute($listing, 'Medium', 'Apparel');

        return $listing;
    }

    /**
     * Two dimension axes with a sparse, hand-priced matrix — only the
     * combinations the seller actually sells get a variant row, each with a
     * `price_override_cents` rather than a summed surcharge, the primitive
     * that replaces the 136-cell hand-priced matrix — crossed with a third,
     * dense Wood axis: every priced cell exists in both Walnut and Oak.
     */
    private function walnutTable(Seller $seller): Listing
    {
        $listing = $this->createListing(
            $seller,
            'Live-Edge Great Hall Dining Table',
            'A single-slab live-edge walnut table, oiled and ready for the dining hall.',
            'Length x width, see options',
            80000,
            1,
            category: Category::where('name', 'Furniture')->sole(),
        );

        $length = $this->axis($listing, 'Length');
        $l36 = $this->value($length, '36 in', 0);
        $l48 = $this->value($length, '48 in', 0);
        $l60 = $this->value($length, '60 in', 0);

        $width = $this->axis($listing, 'Width');
        $w24 = $this->value($width, '24 in', 0);
        $w30 = $this->value($width, '30 in', 0);

        // Which wood is the buyer's choice, not a second attribute vocabulary
        // (FEAT-031): the attribute below says Wood, this axis says which.
        // Catalog-backed (FEAT-032): the axis references Wood Species and
        // each option value references its catalog property_value_id, so
        // the walnut variant is structurally the walnut one (§2.1).
        $woodSpeciesProperty = Property::where('name', 'Wood Species')->sole();
        $wood = $this->axis($listing, 'Wood', $woodSpeciesProperty);
        $walnut = $this->value($wood, 'Walnut', 0, isDefault: true, propertyValue: $this->propertyValue($woodSpeciesProperty, 'Walnut'));
        $oak = $this->value($wood, 'Oak', 0, propertyValue: $this->propertyValue($woodSpeciesProperty, 'Oak'));

        $createVariant = app(CreateVariant::class);
        // Sparse: four of the six possible Length x Width cells, each
        // hand-priced. 36x30 and 60x24 are never created — a seller who does
        // not offer them adds no row for them, rather than materializing
        // every cell of the grid. Each priced cell carries its price to both
        // Wood options — the wood choice is stylistic, not a size surcharge —
        // so the sparse grid crosses with the full Wood axis.
        $createVariant($listing, [$l36, $w24, $walnut], priceOverrideCents: 80000);
        $createVariant($listing, [$l36, $w24, $oak], priceOverrideCents: 80000);
        $createVariant($listing, [$l48, $w24, $walnut], priceOverrideCents: 95000);
        $createVariant($listing, [$l48, $w24, $oak], priceOverrideCents: 95000);
        $createVariant($listing, [$l48, $w30, $walnut], priceOverrideCents: 110000);
        $createVariant($listing, [$l48, $w30, $oak], priceOverrideCents: 110000);
        $createVariant($listing, [$l60, $w30, $walnut], priceOverrideCents: 135000);
        $createVariant($listing, [$l60, $w30, $oak], priceOverrideCents: 135000);

        $this->attribute($listing, 'Medium', 'Wood');

        app(AddDescriptionSection::class)($listing, 0, DescriptionSectionKind::Care, 'Care', 'Oil every six months with food-safe mineral oil. Wipe spills promptly — walnut marks with standing water.');

        return $listing;
    }

    /**
     * One serialized variant with no axes: the 52-option-axis-of-numbered-lots
     * hack, fixed. Twelve units, each with its own condition and measured
     * specs; the variant's available quantity derives from them rather than
     * a stored number.
     */
    private function vintageCandlesticks(Seller $seller): Listing
    {
        $listing = $this->createListing(
            $seller,
            'Great Hall Brass Candlesticks, Individually Listed',
            'A lot of vintage brass candlesticks, each sold individually — pick the one photographed.',
            'Approx. 8-10 in tall, varies by piece',
            4500,
            12,
            category: Category::where('name', 'Home Goods')->sole(),
        );

        $variant = app(CreateVariant::class)($listing, [], isSerialized: true);

        $addUnit = app(AddUnit::class);

        for ($i = 1; $i <= 12; $i++) {
            $addUnit(
                $variant,
                "#{$i}",
                conditionNote: $i % 4 === 0 ? 'Small chip at base, priced accordingly' : 'Excellent estate condition',
                specs: ['height_mm' => 200 + $i * 5, 'weight_g' => 300 + $i * 10],
                priceOverrideCents: $i % 4 === 0 ? 3500 : null,
            );
        }

        $this->attribute($listing, 'Medium', 'Metal');

        return $listing;
    }

    /**
     * A size axis, a priced paper-stock select modifier, and quantity-break
     * tiers standing in for the fake variation-as-tier hack.
     */
    private function weddingInvitations(Seller $seller): Listing
    {
        $listing = $this->createListing(
            $seller,
            'Letterpress Yule Ball Invitations',
            'Letterpress-printed Yule Ball invitations, sold per card with tiered pricing for larger orders.',
            'Card only, envelope included',
            300,
            500,
            category: Category::where('name', 'Stationery')->sole(),
        );

        $size = $this->axis($listing, 'Size');
        $this->value($size, '4x6 in', 0, isDefault: true);
        $this->value($size, '5x7 in', 50);

        app(GenerateVariants::class)($listing);

        $paperStock = app(CreateModifier::class)($listing, ModifierKind::Select, 'Paper Stock', required: true);
        app(AddModifierOption::class)($paperStock, 'Standard', 0, 0);
        app(AddModifierOption::class)($paperStock, 'Pearl Shimmer', 50, 1);
        app(AddModifierOption::class)($paperStock, 'Cotton Linen', 100, 2);

        $addQuantityBreak = app(AddQuantityBreak::class);
        $addQuantityBreak($listing, 50, 500);
        $addQuantityBreak($listing, 100, 1000);
        $addQuantityBreak($listing, 200, 1500);

        app(AddDescriptionSection::class)($listing, 0, DescriptionSectionKind::Faq, 'FAQ', bodyJson: [
            ['q' => 'How long does printing take?', 'a' => 'Orders ship 2-3 weeks after proof approval.'],
        ]);

        $this->attribute($listing, 'Medium', 'Paper');

        return $listing;
    }

    /**
     * Pet count and pose as two independent axes — each option label carries
     * one decision, rather than the "1 Pet, Sitting" / "2 Pets, Sitting
     * Together" compound-string hack (IMPRV-010) — alongside a size/framing
     * axis where the two values are genuinely distinct products. Three axes,
     * generated as their full cross product.
     */
    private function petPortrait(Seller $seller): Listing
    {
        $listing = $this->createListing(
            $seller,
            'Custom Patronus Portrait',
            'A hand-painted portrait of your patronus (or two), from your favorite memory.',
            'See size options',
            6500,
            15,
            category: Category::where('name', 'Art')->sole(),
        );

        $pets = $this->axis($listing, 'Pets');
        $this->value($pets, '1 Pet', 0, isDefault: true);
        $this->value($pets, '2 Pets', 1500);

        $pose = $this->axis($listing, 'Pose');
        $this->value($pose, 'Sitting', 0, isDefault: true);
        $this->value($pose, 'Playful', 0);

        $sizeAndFraming = $this->axis($listing, 'Size & Framing');
        $this->value($sizeAndFraming, '8x10 Print', 0, isDefault: true);
        $this->value($sizeAndFraming, '11x14 Framed', 4000);

        app(GenerateVariants::class)($listing);

        // Art's Medium grant is required (TaxonomySeeder) — this is the
        // archetype that carries it.
        $this->attribute($listing, 'Medium', 'Painting');

        return $listing;
    }

    /**
     * DSGN-002's standalone-pricing archetype: Size is `standalone` — each
     * size just has a price, none is a "base" — crossed with Frame, an
     * ordinary `add_on` axis. `listings.price_cents` is never set directly
     * here; {@see \App\Support\Configurator\ListingPriceSync}, run from
     * inside {@see AddOptionValue}, derives it from the default (8x10)
     * option's price the moment that option is added.
     */
    private function sunsetRidgePrint(Seller $seller): Listing
    {
        $listing = $this->createListing(
            $seller,
            'The Burrow at Sunset, Fine Art Print',
            'A sunset over the Burrow, giclée printed in three sizes — framed or unframed.',
            'See size options',
            1800,
            25,
            category: Category::where('name', 'Art')->sole(),
        );

        $size = $this->axis($listing, 'Size', pricingMode: PricingMode::Standalone);
        $this->value($size, '8x10', 0, isDefault: true, priceCents: 1800);
        $this->value($size, '11x14', 0, priceCents: 2400);
        $this->value($size, '16x20', 0, priceCents: 3400);

        $frame = $this->axis($listing, 'Frame');
        $this->value($frame, 'Unframed', 0, isDefault: true);
        $this->value($frame, 'Black frame', 3200);

        app(GenerateVariants::class)($listing);

        $this->attribute($listing, 'Medium', 'Print');

        return $listing;
    }

    private function createListing(
        Seller $seller,
        string $title,
        string $description,
        string $dimensions,
        int $priceCents,
        int $quantity,
        ?Category $category = null,
    ): Listing {
        $listing = app(CreateListing::class)($seller, ListingDraft::of(
            $title,
            $description,
            $dimensions,
            Money::fromCents($priceCents),
            $quantity,
        ))->changeStatusTo(ListingStatus::ForSale);

        if ($category !== null) {
            $listing->update(['category_id' => $category->id]);
        }

        return $listing;
    }

    private function axis(Listing $listing, string $name, ?Property $property = null, PricingMode $pricingMode = PricingMode::AddOn): OptionAxis
    {
        return app(CreateOptionAxis::class)($listing, $name, $property, pricingMode: $pricingMode);
    }

    private function value(OptionAxis $axis, string $label, int $surchargeCents, bool $isDefault = false, ?PropertyValue $propertyValue = null, ?int $priceCents = null): OptionValue
    {
        return app(AddOptionValue::class)($axis, $label, $surchargeCents, $isDefault, propertyValue: $propertyValue, priceCents: $priceCents);
    }

    /**
     * A catalog property's value by label — the link {@see AddOptionValue}
     * stores as `option_values.property_value_id`.
     */
    private function propertyValue(Property $property, string $label): PropertyValue
    {
        return $property->values()->where('label', $label)->sole();
    }

    /**
     * Writes one listing_attributes row directly — reference data, the way
     * {@see TaxonomySeeder} writes its own rows rather than going through a
     * seller-facing action.
     */
    private function attribute(Listing $listing, string $propertyName, string $label): void
    {
        $property = Property::where('name', $propertyName)->sole();

        ListingAttribute::create([
            'listing_id' => $listing->id,
            'seller_id' => $listing->seller_id,
            'property_id' => $property->id,
            'property_value_id' => $property->values()->where('label', $label)->sole()->id,
        ]);
    }
}
