<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateModifier;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\GenerateVariants;
use App\Domain\Configurator\CartLineFingerprint;
use App\Domain\Configurator\ModifierKind;

it('converts itself into the cart line the listing it holds prices', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 4500]);
    $cart = $this->cartFor($this->anonymousCustomer());
    $item = CartItem::create([
        'cart_id' => $cart->id,
        'listing_id' => $listing->id,
        'quantity' => 3,
        'fingerprint' => CartLineFingerprint::of(null, null, [])->value,
    ]);

    $line = $item->toLine();

    expect($line->sellerId)->toBe($seller->id)
        ->and($line->unitPrice)->toBeMoney(4500)
        ->and($line->quantity)->toBe(3)
        ->and($item->isConfigured())->toBeFalse()
        ->and($item->currentBreakdown()->total())->toBeMoney(13500)
        ->and($item->currentAvailability()->selectable)->toBeTrue();
});

it('re-resolves a configured line price from the live variant', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 12000]);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    $gold = app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    app(GenerateVariants::class)($listing);
    $variant = $listing->variants()->sole();
    $text = app(CreateModifier::class)($listing, ModifierKind::Text, 'Note', addOnPriceCents: 500);
    $cart = $this->cartFor($this->anonymousCustomer());
    $item = CartItem::create([
        'cart_id' => $cart->id,
        'listing_id' => $listing->id,
        'variant_id' => $variant->id,
        'quantity' => 2,
        'configuration_json' => [['axisId' => $metal->id, 'axisName' => 'Metal', 'optionValueId' => $gold->id, 'optionValueLabel' => 'Gold']],
        'answers_json' => [$text->id => ['prompt' => 'Note', 'answer' => 'Congrats!', 'raw' => 'Congrats!']],
        'fingerprint' => CartLineFingerprint::of($variant->id, null, [$text->id => 'Congrats!'])->value,
    ]);

    expect($item->isConfigured())->toBeTrue()
        ->and($item->toLine()->total())->toBeMoney(25000)
        ->and($item->currentBreakdown()->total())->toBeMoney(25000);

    $listing->update(['price_cents' => 15000]);
    $item->refresh();

    expect($item->toLine()->total())->toBeMoney(31000);
});

it('prices a text modifiers flat answer on an axis-free listing, matching a configured lines shape', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 4500]);
    $note = app(CreateModifier::class)($listing, ModifierKind::Text, 'Note', addOnPriceCents: 500);
    $cart = $this->cartFor($this->anonymousCustomer());
    $item = CartItem::create([
        'cart_id' => $cart->id,
        'listing_id' => $listing->id,
        'quantity' => 2,
        'answers_json' => [$note->id => ['prompt' => 'Note', 'answer' => 'Congrats!', 'raw' => 'Congrats!']],
        'fingerprint' => CartLineFingerprint::of(null, null, [$note->id => 'Congrats!'])->value,
    ]);

    expect($item->isConfigured())->toBeFalse()
        ->and($item->currentBreakdown()->total())->toBeMoney(10000)
        ->and($item->toLine()->total())->toBeMoney(10000);
});

it('prices a measurement modifiers rated answer on an axis-free listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 3000]);
    $length = app(CreateModifier::class)($listing, ModifierKind::Measurement, 'Length', unit: 'in', rateCentsPerUnit: 150);
    $cart = $this->cartFor($this->anonymousCustomer());
    $item = CartItem::create([
        'cart_id' => $cart->id,
        'listing_id' => $listing->id,
        'quantity' => 1,
        'answers_json' => [$length->id => ['prompt' => 'Length', 'answer' => '10 in', 'raw' => '10']],
        'fingerprint' => CartLineFingerprint::of(null, null, [$length->id => '10'])->value,
    ]);

    expect($item->isConfigured())->toBeFalse()
        ->and($item->currentBreakdown()->total())->toBeMoney(4500)
        ->and($item->toLine()->total())->toBeMoney(4500);
});

it('reports a configured line unavailable once its variant is disabled', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = app(CreateOptionAxis::class)($listing, 'Color');
    app(AddOptionValue::class)($axis, 'Red', 0, isDefault: true);
    app(GenerateVariants::class)($listing);
    $variant = $listing->variants()->sole();
    $cart = $this->cartFor($this->anonymousCustomer());
    $item = CartItem::create([
        'cart_id' => $cart->id,
        'listing_id' => $listing->id,
        'variant_id' => $variant->id,
        'quantity' => 1,
        'fingerprint' => CartLineFingerprint::of($variant->id, null, [])->value,
    ]);

    expect($item->currentAvailability()->selectable)->toBeTrue();

    $variant->update(['enabled' => false]);
    $item->refresh();

    expect($item->currentAvailability()->selectable)->toBeFalse()
        ->and($item->currentAvailability()->reason)->toBe('not offered');
});
