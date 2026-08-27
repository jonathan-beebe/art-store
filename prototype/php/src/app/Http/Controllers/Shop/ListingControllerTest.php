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
use App\Domain\Configurator\ModifierKind;
use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
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
        'medium' => 'oil',
        'dimensions' => '12 x 16 in',
        'price_cents' => 24500,
    ]);

    $response = $this->get('/art/'.$listing->slug);

    $response->assertOk();
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('A quiet morning over the water.');
    $response->assertSee('oil');
    $response->assertSee('12 x 16 in');
    $response->assertSee('$245.00');
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

it('preselects the rings axis defaults and prices the page concretely at first paint', function (): void {
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

it('renders the candlesticks as a unit picker excluding sold pieces', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'candlesticks', 'price_cents' => 4500]);
    $variant = app(CreateVariant::class)($listing, [], isSerialized: true);
    $addUnit = app(AddUnit::class);
    $addUnit($variant, '#1', conditionNote: 'Excellent estate condition');
    $sold = $addUnit($variant, '#2', priceOverrideCents: 3500);
    $sold->update(['state' => 'sold']);

    $response = $this->get('/art/candlesticks');

    $response->assertOk();
    $response->assertSee('#1');
    $response->assertSee('Excellent estate condition');
    $response->assertDontSee('#2');
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
