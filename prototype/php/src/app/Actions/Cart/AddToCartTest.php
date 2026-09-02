<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateModifier;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\GenerateVariants;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsReport;
use App\Domain\Configurator\ModifierKind;
use App\Domain\DomainRuleViolation;
use App\Models\CustomerBlock;
use App\Models\ListingRemoval;
use DomainException;

it('puts a listing in the cart', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartFor($customer);
    $listing = $this->listing($this->seller(), ['quantity' => 3]);

    $item = app(AddToCart::class)($cart, $listing, 2, $this->moment('2026-08-20 09:00:00'));

    expect($item->listing_id)->toBe($listing->id)
        ->and($item->quantity)->toBe(2);
});

it('raises the quantity on one line when the same listing is added twice', function (): void {
    $cart = $this->cartFor($this->verifiedCustomer());
    $listing = $this->listing($this->seller(), ['quantity' => 5]);
    $addToCart = app(AddToCart::class);
    $now = $this->moment('2026-08-20 09:00:00');

    $addToCart($cart, $listing, 1, $now);
    $item = $addToCart($cart, $listing, 2, $now);

    expect($item->quantity)->toBe(3)
        ->and($cart->items()->count())->toBe(1);
});

it('caps the quantity at the stock the listing has left', function (): void {
    $cart = $this->cartFor($this->verifiedCustomer());
    $listing = $this->listing($this->seller(), ['quantity' => 2]);

    $item = app(AddToCart::class)($cart, $listing, 9, $this->moment('2026-08-20 09:00:00'));

    expect($item->quantity)->toBe(2);
});

it('refuses a sold out listing', function (): void {
    $cart = $this->cartFor($this->verifiedCustomer());
    $listing = $this->listing($this->seller(), ['quantity' => 0]);

    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 09:00:00'));
})->throws(DomainException::class);

it('refuses a listing an admin has removed from the storefront', function (): void {
    $cart = $this->cartFor($this->verifiedCustomer());
    $listing = $this->listing($this->seller(), ['quantity' => 3]);
    ListingRemoval::factory()->create(['listing_id' => $listing->id]);

    $add = fn () => app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 09:00:00'));

    expect($add)->toThrow(DomainRuleViolation::class, 'That listing is no longer for sale.')
        ->and($cart->items()->count())->toBe(0);
});

it('takes a listing whose removal was lifted', function (): void {
    $cart = $this->cartFor($this->verifiedCustomer());
    $listing = $this->listing($this->seller(), ['quantity' => 3]);
    ListingRemoval::factory()->lifted()->create(['listing_id' => $listing->id]);

    $item = app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 09:00:00'));

    expect($item->quantity)->toBe(1);
});

it('refuses a blocked customer', function (): void {
    $customer = $this->verifiedCustomer();
    CustomerBlock::factory()->create(['customer_id' => $customer->id, 'reason' => 'Chargeback fraud.']);
    $cart = $this->cartFor($customer);
    $listing = $this->listing($this->seller());

    $add = fn () => app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 09:00:00'));

    expect($add)->toThrow(DomainRuleViolation::class, 'Chargeback fraud.')
        ->and($cart->items()->count())->toBe(0);
});

it('records the add as a listing event', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartFor($customer);
    $listing = $this->listing($this->seller());

    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 09:00:00'));
    app(Analytics::class)->flush();

    expect(AnalyticsReport::countsForListing($listing->id)->cartAdds)->toBe(1);
});

it('clamps a configured line’s quantity to the variant’s own stock, not the listing’s', function (): void {
    $cart = $this->cartFor($this->verifiedCustomer());
    $listing = $this->listing($this->seller());
    $axis = app(CreateOptionAxis::class)($listing, 'Size');
    app(AddOptionValue::class)($axis, 'M', 0, isDefault: true);
    app(GenerateVariants::class)($listing);
    $variant = $listing->variants()->sole();
    $variant->update(['quantity' => 2]);

    $item = app(AddToCart::class)($cart, $listing, 9, $this->moment('2026-08-20 09:00:00'), listingHasVariants: true, variant: $variant);

    expect($item->quantity)->toBe(2);
});

it('gives two distinct configurations of the same listing separate cart lines', function (): void {
    $cart = $this->cartFor($this->verifiedCustomer());
    $listing = $this->listing($this->seller(), ['quantity' => 5]);
    $note = app(CreateModifier::class)($listing, ModifierKind::Text, 'Note', addOnPriceCents: 300);
    $addToCart = app(AddToCart::class);
    $now = $this->moment('2026-08-20 09:00:00');

    $addToCart(
        $cart, $listing, 1, $now,
        answers: [$note->id => ['prompt' => 'Note', 'answer' => 'Happy Birthday', 'raw' => 'Happy Birthday']],
        fingerprintAnswers: [$note->id => 'Happy Birthday'],
    );
    $addToCart(
        $cart, $listing, 1, $now,
        answers: [$note->id => ['prompt' => 'Note', 'answer' => 'Congrats!', 'raw' => 'Congrats!']],
        fingerprintAnswers: [$note->id => 'Congrats!'],
    );

    expect($cart->items()->count())->toBe(2);
});
