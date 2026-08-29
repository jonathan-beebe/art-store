<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Configurator\AddModifierOption;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\AddQuantityBreak;
use App\Actions\Configurator\AddUnit;
use App\Actions\Configurator\CreateModifier;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\CreateVariant;
use App\Actions\Configurator\GenerateVariants;
use App\Actions\Configurator\ScopeModifier;
use App\Domain\Configurator\DescriptionSectionKind;
use App\Domain\Configurator\ModifierKind;
use App\Domain\Configurator\PricingMode;
use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Models\DescriptionSection;
use App\Models\ListingAttribute;
use App\Models\ListingEvent;
use App\Models\ListingFaq;
use App\Models\ListingRemoval;
use App\Models\Property;
use App\Models\PropertyValue;
use Tests\CapturedStory;

it('shows the listing in full', function (): void {
    $listing = $this->listing($this->seller('Blue Kiln Studio'), [
        'title' => 'Harbour at Dawn',
        'slug' => 'harbour-at-dawn',
        'description' => 'A quiet morning over the water.',
        'dimensions' => '12 x 16 in',
        'price_cents' => 24500,
    ]);
    $this->mediumAttribute($listing, 'Oil');

    $response = $this->get('/art/'.$listing->slug);

    $response->assertOk();
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('A quiet morning over the water.');
    $response->assertSee('Oil');
    $response->assertSee('12 x 16 in');
    $response->assertSee('$245.00');
});

it('renders the cover and a thumbnail grid of the remaining images', function (): void {
    $listing = $this->listing($this->seller(), ['slug' => 'gallery-piece']);
    $cover = $this->listingImage($listing, ['path' => 'listings/cover.jpg', 'position' => 0]);
    $second = $this->listingImage($listing, ['path' => 'listings/second.jpg', 'position' => 1]);

    $response = $this->get('/art/gallery-piece');

    $response->assertSeeInOrder([$cover->url(), $second->url()], escape: false);
});

it('renders no thumbnail grid for a listing with a single image', function (): void {
    $listing = $this->listing($this->seller(), ['slug' => 'lone-image']);
    $this->listingImage($listing, ['position' => 0]);

    $response = $this->get('/art/lone-image');

    $response->assertOk();
    $response->assertDontSee('photo 2', false);
});

it('shows the Medium attribute when the listing carries one', function (): void {
    $listing = $this->listing($this->seller(), ['slug' => 'kiln-study']);
    $this->mediumAttribute($listing, 'Ceramic');

    $response = $this->get('/art/kiln-study');

    $response->assertSee('Ceramic');
});

it('shows no Medium line for a listing with no Medium attribute', function (): void {
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->get('/art/harbour-at-dawn');

    $response->assertDontSee('Medium');
});

it('records a view event for the visitor', function (): void {
    $visitor = $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $this->get('/art/harbour-at-dawn');

    $event = ListingEvent::sole();
    expect($event->type)->toBe(ListingEventType::View)
        ->and($event->listing_id)->toBe($listing->id)
        ->and($event->customer_id)->toBe($visitor->id);
});

it('collapses a second view within the hour into no row, logged as a refusal', function (): void {
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    $log = CapturedStory::capture();

    $this->travelTo($this->moment('2026-08-20 09:00:00'));
    $first = $this->get('/art/harbour-at-dawn');
    $visitorCookie = $first->getCookie('customer_id')?->getValue();
    assert(is_string($visitorCookie));

    $this->travelTo($this->moment('2026-08-20 09:45:00'));
    $this->withCookie('customer_id', $visitorCookie)->get('/art/harbour-at-dawn');

    expect(ListingEvent::query()->where('type', ListingEventType::View)->count())->toBe(1);

    $refused = $log->line('listing.view', 'refused');
    expect($refused['level'])->toBe('debug');
});

it('records a view in the next hour as a row and a did line of its own', function (): void {
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $this->travelTo($this->moment('2026-08-20 09:00:00'));
    $first = $this->get('/art/harbour-at-dawn');
    $visitorCookie = $first->getCookie('customer_id')?->getValue();
    assert(is_string($visitorCookie));

    $this->travelTo($this->moment('2026-08-20 10:00:00'));
    $this->withCookie('customer_id', $visitorCookie)->get('/art/harbour-at-dawn');

    expect(ListingEvent::query()->where('type', ListingEventType::View)->count())->toBe(2);
});

it('says a sold listing is sold and offers no cart button', function (): void {
    $this->listing($this->seller(), [
        'slug' => 'sold-vase',
        'status' => ListingStatus::Sold,
        'quantity' => 0,
    ]);

    $response = $this->get('/art/sold-vase');

    $response->assertOk();
    $response->assertSee('Sold');
    $response->assertDontSee('Add to cart');
});

it('keeps a draft listing off the storefront', function (): void {
    $this->listing($this->seller(), ['slug' => 'unfinished', 'status' => ListingStatus::Draft]);

    $this->get('/art/unfinished')->assertNotFound();
});

it('answers the same 404 for a removed listing, whatever its status says', function (): void {
    $listing = $this->listing($this->seller(), ['slug' => 'recalled-print']);
    ListingRemoval::factory()->create(['listing_id' => $listing->id]);

    $this->get('/art/recalled-print')->assertNotFound();
});

it('answers the same 404 for an unknown slug as for a removed one', function (): void {
    $removed = $this->listing($this->seller(), ['slug' => 'recalled-print']);
    ListingRemoval::factory()->create(['listing_id' => $removed->id]);

    $unknown = $this->get('/art/does-not-exist');
    $removedResponse = $this->get('/art/recalled-print');

    $unknown->assertNotFound();
    $removedResponse->assertNotFound();
    expect($unknown->status())->toBe($removedResponse->status());
});

it('offers a form to ask the seller a question', function (): void {
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->get('/art/harbour-at-dawn');

    $response->assertSee('Ask the seller a question');
    $response->assertSee(route('shop.listing.questions', $listing), escape: false);
});

it('lists the sellers published questions and answers', function (): void {
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    ListingFaq::factory()->create([
        'listing_id' => $listing->id,
        'question' => 'Does this ship framed?',
        'answer' => 'Yes, in a black wood frame.',
        'published_at' => $this->moment('2026-08-20 09:00:00'),
    ]);

    $response = $this->get('/art/harbour-at-dawn');

    $response->assertSee('Does this ship framed?');
    $response->assertSee('Yes, in a black wood frame.');
});

it('shows no questions and answers section for a listing with none published', function (): void {
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->get('/art/harbour-at-dawn');

    $response->assertDontSee('Questions &amp; answers', escape: false);
});

it('D1: renders the listings page sections as separated titled blocks in order', function (): void {
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    DescriptionSection::factory()->create([
        'listing_id' => $listing->id,
        'position' => 0,
        'kind' => DescriptionSectionKind::Text,
        'title' => 'How to order',
        'body_md' => 'Orders print Mondays.',
    ]);
    DescriptionSection::factory()->create([
        'listing_id' => $listing->id,
        'position' => 1,
        'kind' => DescriptionSectionKind::Care,
        'title' => 'Care',
        'body_md' => 'Hand wash cold.',
    ]);

    $response = $this->get('/art/harbour-at-dawn');

    $response->assertSeeInOrder(['How to order', 'Orders print Mondays.', 'Care', 'Hand wash cold.']);
});

it('D3: renders a size chart on the listing page as a real table', function (): void {
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    DescriptionSection::factory()->json(DescriptionSectionKind::SizeChart, [
        ['label' => 'S', 'value1' => '36 in', 'value2' => '27 in'],
        ['label' => 'M', 'value1' => '40 in', 'value2' => '28 in'],
    ])->create(['listing_id' => $listing->id, 'position' => 0, 'title' => 'Size chart']);

    $response = $this->get('/art/harbour-at-dawn');

    $response->assertSee('<table', false);
    $response->assertSeeInOrder(['S', '36 in', '27 in', 'M', '40 in', '28 in']);
});

it('shows no page sections for a listing with none', function (): void {
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->get('/art/harbour-at-dawn');

    $response->assertDontSee('<table', false);
});

it('A10: preselects the rings axis defaults and prices the page concretely at first paint, one whole price never a range', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'ring', 'price_cents' => 12000]);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    $roseGold = app(AddOptionValue::class)($metal, 'Rose Gold', 800);
    $engraving = app(CreateOptionAxis::class)($listing, 'Engraving');
    app(AddOptionValue::class)($engraving, 'No Engraving', 0, isDefault: true);
    $outside = app(AddOptionValue::class)($engraving, 'Outside Only', 500);
    app(GenerateVariants::class)($listing);
    $font = app(CreateModifier::class)($listing, ModifierKind::Select, 'Engraving Font', required: true);
    app(AddModifierOption::class)($font, 'Block', 0, 0);
    $text = app(CreateModifier::class)($listing, ModifierKind::Text, 'Engraving Text', required: true, charLimit: 20);
    app(ScopeModifier::class)($font, [$outside]);
    app(ScopeModifier::class)($text, [$outside]);

    $default = $this->get('/art/ring');

    $default->assertOk();
    $default->assertSee('$120.00');
    $default->assertDontSee('Engraving Font');
    $default->assertDontSee('Engraving Text');

    $withRoseGoldAndEngraving = $this->get('/art/ring?'.http_build_query([
        'axis' => [$metal->id => $roseGold->id, $engraving->id => $outside->id],
    ]));

    $withRoseGoldAndEngraving->assertOk();
    $withRoseGoldAndEngraving->assertSee('$133.00');
    $withRoseGoldAndEngraving->assertSee('Engraving Font');
    $withRoseGoldAndEngraving->assertSee('Engraving Text');
});

it('shows a standalone size’s absolute prices and an absolute-first breakdown', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'sunset-ridge', 'price_cents' => 1800]);
    $size = app(CreateOptionAxis::class)($listing, 'Size', pricingMode: PricingMode::Standalone);
    app(AddOptionValue::class)($size, '8x10', isDefault: true, priceCents: 1800);
    $elevenByFourteen = app(AddOptionValue::class)($size, '11x14', priceCents: 2400);
    $frame = app(CreateOptionAxis::class)($listing, 'Frame');
    app(AddOptionValue::class)($frame, 'Unframed', 0, isDefault: true);
    app(AddOptionValue::class)($frame, 'Black frame', 3200);
    app(GenerateVariants::class)($listing);

    $default = $this->get('/art/sunset-ridge');

    $default->assertOk();
    // The selected 8x10 and the non-selected 11x14 both show their own
    // absolute price, never a delta off one another.
    $default->assertSee('($18.00)', escape: false);
    $default->assertSee('($24.00)', escape: false);
    $default->assertSee('Size: 8x10', escape: false);
    $default->assertSee('Frame: Unframed', escape: false);

    $withEleven = $this->get('/art/sunset-ridge?'.http_build_query(['axis' => [$size->id => $elevenByFourteen->id]]));

    $withEleven->assertOk();
    // Now 11x14 is selected and 8x10 is the non-selected one — both still
    // show their own absolute price.
    $withEleven->assertSee('($18.00)', escape: false);
    $withEleven->assertSee('($24.00)', escape: false);
    $withEleven->assertSee('Size: 11x14', escape: false);
});

it('shows the mugs personalization text box only once the personalized option is selected', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'mug', 'price_cents' => 1800]);
    $personalization = app(CreateOptionAxis::class)($listing, 'Personalization');
    app(AddOptionValue::class)($personalization, 'Blank', 0, isDefault: true);
    $personalized = app(AddOptionValue::class)($personalization, 'Personalized', 300);
    app(GenerateVariants::class)($listing);
    $text = app(CreateModifier::class)($listing, ModifierKind::Text, 'Personalization Text', required: true, charLimit: 16);
    app(ScopeModifier::class)($text, [$personalized]);

    $blank = $this->get('/art/mug');
    $blank->assertOk();
    $blank->assertDontSee('Personalization Text');

    $withPersonalized = $this->get('/art/mug?'.http_build_query(['axis' => [$personalization->id => $personalized->id]]));
    $withPersonalized->assertOk();
    $withPersonalized->assertSee('Personalization Text');
    $withPersonalized->assertSee('$21.00');
});

it('shows the tees larger-size surcharge inline', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'tee', 'price_cents' => 2200]);
    $color = app(CreateOptionAxis::class)($listing, 'Color');
    app(AddOptionValue::class)($color, 'Black', 0, isDefault: true);
    $size = app(CreateOptionAxis::class)($listing, 'Size');
    app(AddOptionValue::class)($size, 'M', 0, isDefault: true);
    $xl = app(AddOptionValue::class)($size, 'XL', 150);
    app(GenerateVariants::class)($listing);

    $response = $this->get('/art/tee');

    $response->assertOk();
    $response->assertSee('(+$1.50)', escape: false);

    $withXl = $this->get('/art/tee?'.http_build_query(['axis' => [$size->id => $xl->id]]));
    $withXl->assertSee('$23.50');
});

it('greys out a sparse combination the table seller never priced, with a not-offered reason', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'table', 'price_cents' => 80000]);
    $length = app(CreateOptionAxis::class)($listing, 'Length');
    $l36 = app(AddOptionValue::class)($length, '36 in', 0, isDefault: true);
    $l48 = app(AddOptionValue::class)($length, '48 in', 0);
    $width = app(CreateOptionAxis::class)($listing, 'Width');
    $w24 = app(AddOptionValue::class)($width, '24 in', 0, isDefault: true);
    $w30 = app(AddOptionValue::class)($width, '30 in', 0);
    $createVariant = app(CreateVariant::class);
    $createVariant($listing, [$l36, $w24], priceOverrideCents: 80000);
    $createVariant($listing, [$l48, $w30], priceOverrideCents: 110000);

    $response = $this->get('/art/table?'.http_build_query(['axis' => [$length->id => $l48->id]]));

    $response->assertOk();
    $response->assertSee('not offered');
    $response->assertSee('disabled', escape: false);
});

it('renders the candlesticks as a unit picker excluding sold pieces, naturally ordered with humanized specs', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'candlesticks', 'price_cents' => 4500]);
    $variant = app(CreateVariant::class)($listing, [], isSerialized: true);
    $addUnit = app(AddUnit::class);
    $addUnit($variant, '#10');
    $addUnit($variant, '#1', conditionNote: 'Excellent estate condition', specs: ['height_mm' => 205, 'weight_g' => 310]);
    $sold = $addUnit($variant, '#2', priceOverrideCents: 3500);
    $sold->update(['state' => 'sold']);

    $response = $this->get('/art/candlesticks');

    $response->assertOk();
    $response->assertSee('Excellent estate condition');
    $response->assertSee('Height: 205 mm');
    $response->assertSee('Weight: 310 g');
    $response->assertSeeInOrder(['#1', '#10']);
    $response->assertDontSee('#2');
});

it('labels an overridden variant’s breakdown with its combination instead of "Base price"', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'table', 'price_cents' => 80000]);
    $length = app(CreateOptionAxis::class)($listing, 'Length');
    $l48 = app(AddOptionValue::class)($length, '48 in', 0, isDefault: true);
    $width = app(CreateOptionAxis::class)($listing, 'Width');
    $w30 = app(AddOptionValue::class)($width, '30 in', 0, isDefault: true);
    app(CreateVariant::class)($listing, [$l48, $w30], priceOverrideCents: 110000);

    $response = $this->get('/art/table');

    $response->assertOk();
    $response->assertSee('48 in / 30 in');
    $response->assertDontSee('Base price');
});

it('shows the wedding invitations quantity-break table and applies the tier live', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'invitations', 'price_cents' => 300]);
    $size = app(CreateOptionAxis::class)($listing, 'Size');
    app(AddOptionValue::class)($size, '4x6 in', 0, isDefault: true);
    app(GenerateVariants::class)($listing);
    app(AddQuantityBreak::class)($listing, 50, 500);
    app(AddQuantityBreak::class)($listing, 100, 1000);

    $response = $this->get('/art/invitations?quantity=100');

    $response->assertOk();
    $response->assertSee('50+');
    $response->assertSee('100+');
    $response->assertSee('Quantity discount (100+)');
    $response->assertSee('$270.00');
});

it('renders a Highlights panel from the listings attributes', function (): void {
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    $material = Property::factory()->create(['name' => 'Material']);
    $walnut = PropertyValue::factory()->create(['property_id' => $material->id, 'label' => 'Walnut']);
    ListingAttribute::factory()->create(['listing_id' => $listing->id, 'property_id' => $material->id, 'property_value_id' => $walnut->id]);

    $response = $this->get('/art/harbour-at-dawn');

    $response->assertSee('Highlights');
    $response->assertSee('Material');
    $response->assertSee('Walnut');
});

it('renders no Highlights panel for a listing with no attributes', function (): void {
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->get('/art/harbour-at-dawn');

    $response->assertDontSee('Highlights');
});

it('keeps the legacy zero-axis listing on its one-click add, with no configurator section', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'print', 'price_cents' => 3500]);

    $response = $this->get('/art/print');

    $response->assertOk();
    $response->assertDontSee('Update options');
    $response->assertSee('Add to cart');
});

it('ships the configurator auto-submit script on the listing page', function (): void {
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->get('/art/harbour-at-dawn');

    $response->assertSee('configurator-autosubmit.js', false);
});

it('keeps a typed modifier answer on the page after a GET refresh', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'mug', 'price_cents' => 1800]);
    $personalization = app(CreateOptionAxis::class)($listing, 'Personalization');
    app(AddOptionValue::class)($personalization, 'Blank', 0, isDefault: true);
    $personalized = app(AddOptionValue::class)($personalization, 'Personalized', 300);
    app(GenerateVariants::class)($listing);
    $text = app(CreateModifier::class)($listing, ModifierKind::Text, 'Personalization Text', required: true, charLimit: 16);
    app(ScopeModifier::class)($text, [$personalized]);

    $response = $this->get('/art/mug?'.http_build_query([
        'axis' => [$personalization->id => $personalized->id],
        'modifier' => [$text->id => 'Ada'],
    ]));

    $response->assertOk();
    $response->assertSee('value="Ada"', false);
});

it('autofocuses the axis select named by the refresh, so a shopper does not lose their place', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'ring', 'price_cents' => 12000]);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    $roseGold = app(AddOptionValue::class)($metal, 'Rose Gold', 800);
    app(GenerateVariants::class)($listing);

    $response = $this->get('/art/ring?'.http_build_query([
        'axis' => [$metal->id => $roseGold->id],
        'focus' => 'axis-'.$metal->id,
    ]));

    $response->assertOk();
    expect($response->getContent())->toMatch(
        '/<select id="axis-'.preg_quote($metal->id, '/').'"[^>]*\bautofocus\b/'
    );
});

it('renders no autofocus on any control when nothing named the refresh', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'ring', 'price_cents' => 12000]);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    app(AddOptionValue::class)($metal, 'Rose Gold', 800);
    app(GenerateVariants::class)($listing);

    $response = $this->get('/art/ring');

    $response->assertOk();
    $response->assertDontSee('autofocus', false);
});
