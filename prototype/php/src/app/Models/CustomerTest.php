<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Cart\AddToCart;
use App\Domain\Customers\StandingFilter;
use App\Domain\Money\Money;
use App\Notifications\ItemSold;
use App\Notifications\OrderShipped;

it('is anonymous when it has no email', function (): void {
    expect((new Customer)->isAnonymous())->toBeTrue();
});

it('is not anonymous once it has an email', function (): void {
    $customer = new Customer(['email' => 'shopper@example.com']);

    expect($customer->isAnonymous())->toBeFalse();
});

it('is verified once its address is confirmed', function (): void {
    expect($this->verifiedCustomer()->isVerified())->toBeTrue()
        ->and($this->anonymousCustomer()->isVerified())->toBeFalse();
});

it('reads the orders it placed', function (): void {
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($this->seller()));
    $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    expect($customer->orders()->pluck('id')->all())->toBe([$order->id]);
});

it('reads the carts it filled', function (): void {
    $customer = $this->anonymousCustomer();
    $cart = $this->cartFor($customer);
    $this->cartFor($this->anonymousCustomer());

    expect($customer->carts()->pluck('id')->all())->toBe([$cart->id]);
});

it('reads its favorites and the listings behind them', function (): void {
    $customer = $this->anonymousCustomer();
    $listing = $this->listing($this->seller());
    $this->listing($this->seller());
    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => $listing->id]);

    expect($customer->favorites()->count())->toBe(1)
        ->and($customer->favoriteListings()->pluck('listings.id')->all())->toBe([$listing->id]);
});

it('reads the notifications addressed to it', function (): void {
    $customer = $this->verifiedCustomer();
    $customer->notify(new OrderShipped('ord_00000000000000000000000004', 'USPS', '94001'));
    $this->seller()->notify(new ItemSold('ord_00000000000000000000000005', Money::fromCents(9000)));

    expect($customer->notifications()->count())->toBe(1)
        ->and($customer->unreadNotifications()->count())->toBe(1);
});

it('is named by the morph alias its notifications are addressed to', function (): void {
    expect((new Customer)->getMorphClass())->toBe('customer');
});

it('reads the conversations it is a participant in', function (): void {
    $customer = $this->anonymousCustomer();
    Conversation::factory()->listingQuestion()->create(['customer_id' => $customer->id]);
    Conversation::factory()->listingQuestion()->create();

    expect($customer->conversations()->count())->toBe(1);
});

it('reads the messages it sent', function (): void {
    $customer = $this->anonymousCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create(['customer_id' => $customer->id]);
    Message::factory()->from($customer)->create(['conversation_id' => $conversation->id]);
    Message::factory()->create(['conversation_id' => $conversation->id]);

    expect($customer->sentMessages()->count())->toBe(1);
});

it('reads the listing events it left', function (): void {
    $customer = $this->anonymousCustomer();
    ListingEvent::factory()->create([
        'listing_id' => $this->listing($this->seller())->id,
        'customer_id' => $customer->id,
        'occurred_at' => $this->moment('2026-08-20 09:00:00'),
    ]);

    expect($customer->listingEvents()->count())->toBe(1);
});

it('gives a customer without a cart one', function (): void {
    $customer = $this->anonymousCustomer();

    $cart = $customer->currentCart();

    expect($cart->customer_id)->toBe($customer->id)
        ->and(Cart::count())->toBe(1);
});

it('returns the same cart twice', function (): void {
    $customer = $this->anonymousCustomer();

    expect($customer->currentCart()->id)->toBe($customer->currentCart()->id);
});

it('picks the cart holding the items after a merge', function (): void {
    $customer = $this->verifiedCustomer();
    $this->cartFor($customer);
    $filled = $this->cartFor($customer);
    CartItem::factory()->create([
        'cart_id' => $filled->id,
        'listing_id' => $this->listing($this->seller())->id,
        'quantity' => 1,
    ]);

    expect($customer->currentCart()->id)->toBe($filled->id);
});

it('can shop with no active block', function (): void {
    $customer = $this->verifiedCustomer();

    expect($customer->canShop())->toBeTrue()
        ->and($customer->currentBlock())->toBeNull()
        ->and($customer->blockReason())->toBeNull();
});

it('cannot shop while a block is active', function (): void {
    $customer = $this->verifiedCustomer();
    CustomerBlock::factory()->create(['customer_id' => $customer->id, 'reason' => 'Chargeback fraud.']);

    expect($customer->canShop())->toBeFalse()
        ->and($customer->currentBlock()?->reason)->toBe('Chargeback fraud.')
        ->and($customer->blockReason())->toBe('Chargeback fraud.');
});

it('can shop again once its block is lifted', function (): void {
    $customer = $this->verifiedCustomer();
    CustomerBlock::factory()->lifted()->create(['customer_id' => $customer->id]);

    expect($customer->canShop())->toBeTrue();
});

it('reads only the active block when it has been blocked more than once', function (): void {
    $customer = $this->verifiedCustomer();
    CustomerBlock::factory()->lifted()->create(['customer_id' => $customer->id, 'reason' => 'First block.']);
    CustomerBlock::factory()->create(['customer_id' => $customer->id, 'reason' => 'Second block.']);

    expect($customer->blockReason())->toBe('Second block.');
});

it('names itself by its name, then its address, then its id', function (): void {
    $named = new Customer(['name' => 'Ada Painter', 'email' => 'ada@example.com']);
    $addressed = new Customer(['email' => 'ada@example.com']);
    $anonymous = Customer::factory()->anonymous()->create();

    expect($named->displayName())->toBe('Ada Painter')
        ->and($addressed->displayName())->toBe('ada@example.com')
        ->and($anonymous->displayName())->toBe($anonymous->id);
});

it('reads every line across the carts it holds', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['quantity' => 3]);
    app(AddToCart::class)($this->cartFor($customer), $listing, 2, $this->moment('2026-08-20 08:00:00'));

    expect($customer->cartItems()->count())->toBe(1)
        ->and($customer->cartItems()->sum('quantity'))->toBe(2);
});

it('reads the merges it stands on either side of', function (): void {
    $customer = $this->verifiedCustomer();
    $anonymous = $this->anonymousCustomer();
    CustomerMerge::create(['anonymous_customer_id' => $anonymous->id, 'customer_id' => $customer->id]);

    expect($customer->mergesAsCustomer()->count())->toBe(1)
        ->and($customer->mergesAsAnonymous()->count())->toBe(0)
        ->and($anonymous->mergesAsAnonymous()->count())->toBe(1)
        ->and($anonymous->mergesAsCustomer()->count())->toBe(0);
});

it('narrows to one standing', function (): void {
    $verified = $this->verifiedCustomer();
    $anonymous = $this->anonymousCustomer();
    $blocked = $this->verifiedCustomer();
    CustomerBlock::factory()->create(['customer_id' => $blocked->id, 'reason' => 'Chargeback fraud.']);

    expect(Customer::query()->inStanding(StandingFilter::All)->count())->toBe(3)
        ->and(Customer::query()->inStanding(StandingFilter::Verified)->count())->toBe(2)
        ->and(Customer::query()->inStanding(StandingFilter::Anonymous)->pluck('id')->all())->toBe([$anonymous->id])
        ->and(Customer::query()->inStanding(StandingFilter::Blocked)->pluck('id')->all())->toBe([$blocked->id]);
});
