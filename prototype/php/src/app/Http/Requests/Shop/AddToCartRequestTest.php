<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Actions\Configurator\AddModifierOption;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\AddUnit;
use App\Actions\Configurator\CreateModifier;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\CreateVariant;
use App\Actions\Configurator\GenerateVariants;
use App\Actions\Configurator\ScopeModifier;
use App\Domain\Configurator\ModifierKind;
use App\Models\CartItem;

it('takes one of the listing when the form sends no quantity', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $this->post('/cart/harbour-at-dawn');

    expect(CartItem::sole()->quantity)->toBe(1);
});

it('takes the quantity the form sends', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'quantity' => 5]);

    $this->post('/cart/harbour-at-dawn', ['quantity' => 3]);

    expect(CartItem::sole()->quantity)->toBe(3);
});

it('refuses a quantity that is not a whole number of pieces', function (string|int $quantity): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'quantity' => 5]);

    $response = $this->post('/cart/harbour-at-dawn', ['quantity' => $quantity]);

    $response->assertSessionHasErrors('quantity');
    expect(CartItem::count())->toBe(0);
})->with([
    'none at all' => [0],
    'a negative count' => [-1],
    'a word' => ['two'],
]);

it('refuses a required text modifier left blank', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'mug']);
    $personalization = app(CreateOptionAxis::class)($listing, 'Personalization');
    app(AddOptionValue::class)($personalization, 'Blank', 0, isDefault: true);
    $personalized = app(AddOptionValue::class)($personalization, 'Personalized', 300);
    app(GenerateVariants::class)($listing);
    $text = app(CreateModifier::class)($listing, ModifierKind::Text, 'Personalization Text', required: true, charLimit: 16);
    app(ScopeModifier::class)($text, [$personalized]);

    $response = $this->post('/cart/mug', ['axis' => [$personalization->id => $personalized->id]]);

    $response->assertSessionHasErrors("modifier.{$text->id}");
    expect(CartItem::count())->toBe(0);
});

it('accepts a configuration with its required modifier answered', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'mug']);
    $personalization = app(CreateOptionAxis::class)($listing, 'Personalization');
    app(AddOptionValue::class)($personalization, 'Blank', 0, isDefault: true);
    $personalized = app(AddOptionValue::class)($personalization, 'Personalized', 300);
    app(GenerateVariants::class)($listing);
    $text = app(CreateModifier::class)($listing, ModifierKind::Text, 'Personalization Text', required: true, charLimit: 16);
    app(ScopeModifier::class)($text, [$personalized]);

    $response = $this->post('/cart/mug', [
        'axis' => [$personalization->id => $personalized->id],
        'modifier' => [$text->id => 'Ada'],
    ]);

    $response->assertRedirect(route('shop.cart'));
    $answers = CartItem::sole()->answers_json ?? [];
    expect($answers[$text->id]['raw'])->toBe('Ada');
});

it('refuses a required select modifier submitted with no answer at all', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'ring']);
    $font = app(CreateModifier::class)($listing, ModifierKind::Select, 'Font', required: true);
    app(AddModifierOption::class)($font, 'Block', 0, 0);

    $response = $this->post('/cart/ring');

    $response->assertSessionHasErrors("modifier.{$font->id}");
    expect(CartItem::count())->toBe(0);
});

it('accepts a required select modifier answered with a valid option id', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'ring']);
    $font = app(CreateModifier::class)($listing, ModifierKind::Select, 'Font', required: true);
    $block = app(AddModifierOption::class)($font, 'Block', 0, 0);

    $response = $this->post('/cart/ring', ['modifier' => [$font->id => $block->id]]);

    $response->assertRedirect(route('shop.cart'));
});

it('refuses a missing unit for a serialized variant', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'candlesticks']);
    $variant = app(CreateVariant::class)($listing, [], isSerialized: true);
    app(AddUnit::class)($variant, '#1');

    $response = $this->post('/cart/candlesticks');

    $response->assertSessionHasErrors('unit');
    expect(CartItem::count())->toBe(0);
});

it('refuses a unit that does not belong to the matched variant', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'candlesticks']);
    $variant = app(CreateVariant::class)($listing, [], isSerialized: true);
    app(AddUnit::class)($variant, '#1');
    $otherListing = $this->listing($this->seller(), ['slug' => 'other']);
    $otherVariant = app(CreateVariant::class)($otherListing, [], isSerialized: true);
    $otherUnit = app(AddUnit::class)($otherVariant, '#1');

    $response = $this->post('/cart/candlesticks', ['unit' => $otherUnit->id]);

    $response->assertSessionHasErrors('unit');
    expect(CartItem::count())->toBe(0);
});

it('imposes no rule on a modifier that is not required', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'ring']);
    app(CreateModifier::class)($listing, ModifierKind::Text, 'Gift note', required: false, charLimit: 200);

    $response = $this->post('/cart/ring');

    $response->assertRedirect(route('shop.cart'));
});

it('refuses a required measurement modifier left blank', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'ring']);
    $length = app(CreateModifier::class)($listing, ModifierKind::Measurement, 'Engraved length', required: true);
    $length->update(['unit' => 'in', 'min_value' => 0, 'max_value' => 20, 'rate_cents_per_unit' => 150]);

    $response = $this->post('/cart/ring');

    $response->assertSessionHasErrors("modifier.{$length->id}");
    expect(CartItem::count())->toBe(0);
});

it('accepts a required measurement modifier answered with a number', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'ring']);
    $length = app(CreateModifier::class)($listing, ModifierKind::Measurement, 'Engraved length', required: true);
    $length->update(['unit' => 'in', 'min_value' => 0, 'max_value' => 20, 'rate_cents_per_unit' => 150]);

    $response = $this->post('/cart/ring', ['modifier' => [$length->id => '4']]);

    $response->assertRedirect(route('shop.cart'));
});

it('accepts a valid unit for a serialized variant', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'candlesticks']);
    $variant = app(CreateVariant::class)($listing, [], isSerialized: true);
    $unit = app(AddUnit::class)($variant, '#1');

    $response = $this->post('/cart/candlesticks', ['unit' => $unit->id]);

    $response->assertRedirect(route('shop.cart'));
    expect(CartItem::sole()->unit_id)->toBe($unit->id);
});
