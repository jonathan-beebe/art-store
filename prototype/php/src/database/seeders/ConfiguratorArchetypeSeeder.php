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
use App\Domain\Listings\ListingDraft;
use App\Domain\Listings\ListingStatus;
use App\Domain\Money\Money;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Property;
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
    public const EMAIL = 'configurator-demo@example.com';

    public function run(): void
    {
        if (Seller::where('email', self::EMAIL)->exists()) {
            return;
        }

        $seller = Seller::create([
            'email' => self::EMAIL,
            'name' => 'Configurator Demo',
            'shop_name' => 'Archetype & Co',
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
    }

    /**
     * Zero axes: the legacy path every other listing already takes. No
     * configurator rows follow this one at all.
     */
    private function plainPrint(Seller $seller): Listing
    {
        return $this->createListing(
            $seller,
            'Meadow at Dawn, 8x10 Print',
            'A giclée print of the meadow behind my studio, shot at first light on a spring morning.',
            'print',
            '8 x 10 in',
            3500,
            10,
        );
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
            'Engraved Signet Ring',
            'A solid signet ring, hand-finished and ready for an inside or outside engraving.',
            'metal',
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
            'Stoneware Coffee Mug',
            'A 12oz stoneware mug, glazed in-house, with an optional personalized name or word.',
            'ceramic',
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
            'Line Art Cat Tee',
            'A single-line cat drawing, screen printed on soft ringspun cotton.',
            'apparel',
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

        return $listing;
    }

    /**
     * Two dimension axes with a sparse, hand-priced matrix: only the
     * combinations the seller actually sells get a variant row, each with a
     * `price_override_cents` rather than a summed surcharge — the primitive
     * that replaces the 136-cell hand-priced matrix.
     */
    private function walnutTable(Seller $seller): Listing
    {
        $listing = $this->createListing(
            $seller,
            'Live-Edge Walnut Dining Table',
            'A single-slab live-edge walnut table, oiled and ready for the dining room.',
            'walnut',
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

        $createVariant = app(CreateVariant::class);
        // Sparse: four of the six possible cells, each hand-priced. 36x30 and
        // 60x24 are never created — a seller who does not offer them adds no
        // row for them, rather than materializing every cell of the grid.
        $createVariant($listing, [$l36, $w24], priceOverrideCents: 80000);
        $createVariant($listing, [$l48, $w24], priceOverrideCents: 95000);
        $createVariant($listing, [$l48, $w30], priceOverrideCents: 110000);
        $createVariant($listing, [$l60, $w30], priceOverrideCents: 135000);

        $material = Property::where('name', 'Material')->sole();
        $walnut = $material->values()->where('label', 'Walnut')->sole();
        ListingAttribute::create([
            'listing_id' => $listing->id,
            'property_id' => $material->id,
            'property_value_id' => $walnut->id,
        ]);

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
            'Vintage Brass Candlesticks, Individually Listed',
            'A lot of vintage brass candlesticks, each sold individually — pick the one photographed.',
            'brass',
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

        $material = Property::where('name', 'Material')->sole();
        $brass = $material->values()->where('label', 'Brass')->sole();
        ListingAttribute::create([
            'listing_id' => $listing->id,
            'property_id' => $material->id,
            'property_value_id' => $brass->id,
        ]);

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
            'Letterpress Wedding Invitations',
            'Letterpress-printed wedding invitations, sold per card with tiered pricing for larger orders.',
            'paper',
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

        return $listing;
    }

    /**
     * A pets-and-pose axis kept as one deliberate creative choice rather than
     * a cross product of pet count and pose, alongside a size/framing axis —
     * two axes, generated as their full cross product.
     */
    private function petPortrait(Seller $seller): Listing
    {
        $listing = $this->createListing(
            $seller,
            'Custom Pet Portrait',
            'A hand-painted portrait of your pet (or two), from your favorite photo.',
            'watercolor',
            'See size options',
            6500,
            15,
            category: Category::where('name', 'Art')->sole(),
        );

        $petsAndPose = $this->axis($listing, 'Pets & Pose');
        $this->value($petsAndPose, '1 Pet, Sitting', 0, isDefault: true);
        $this->value($petsAndPose, '1 Pet, Playful', 0);
        $this->value($petsAndPose, '2 Pets, Sitting Together', 1500);

        $sizeAndFraming = $this->axis($listing, 'Size & Framing');
        $this->value($sizeAndFraming, '8x10 Print', 0, isDefault: true);
        $this->value($sizeAndFraming, '11x14 Framed', 4000);

        app(GenerateVariants::class)($listing);

        return $listing;
    }

    private function createListing(
        Seller $seller,
        string $title,
        string $description,
        string $medium,
        string $dimensions,
        int $priceCents,
        int $quantity,
        ?Category $category = null,
    ): Listing {
        $listing = app(CreateListing::class)($seller, ListingDraft::of(
            $title,
            $description,
            $medium,
            $dimensions,
            Money::fromCents($priceCents),
            $quantity,
        ))->changeStatusTo(ListingStatus::ForSale);

        if ($category !== null) {
            $listing->update(['category_id' => $category->id]);
        }

        return $listing;
    }

    private function axis(Listing $listing, string $name): OptionAxis
    {
        return app(CreateOptionAxis::class)($listing, $name);
    }

    private function value(OptionAxis $axis, string $label, int $surchargeCents, bool $isDefault = false): OptionValue
    {
        return app(AddOptionValue::class)($axis, $label, $surchargeCents, $isDefault);
    }
}
