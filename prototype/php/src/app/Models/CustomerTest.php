<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Cart\AddToCart;
use App\Domain\Customers\StandingFilter;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;

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

it('is named by the morph alias its notifications are addressed to', function (): void {
    expect((new Customer)->getMorphClass())->toBe('customer');
});

it('gives a customer without a cart one', function (): void {
    $customer = $this->anonymousCustomer();

    $cart = $customer->cart();

    expect($cart->customer_id)->toBe($customer->id)
        ->and(Cart::count())->toBe(1);
});

it('reads the customer\'s existing cart rather than creating another', function (): void {
    $customer = $this->verifiedCustomer();
    $existing = $this->cartFor($customer);

    expect($customer->cart()->id)->toBe($existing->id)
        ->and(Cart::count())->toBe(1);
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

it('takes the row a moderation decision is judged against for update', function (): void {
    // SQLite has no row lock and its grammar compiles the clause away, so the
    // query is compiled here with the grammar of a database that does have
    // one — what the same read asks for in production.
    $query = Customer::query()->lockedForModeration()->toBase();

    expect((new MySqlGrammar(DB::connection()))->compileSelect($query))->toEndWith('for update');
});

it('re-reads the locked row rather than trusting the instance it was handed', function (): void {
    $customer = $this->verifiedCustomer();
    Customer::whereKey($customer->id)->update(['name' => 'Rey Alvarez']);

    expect($customer->takeForModeration()->name)->toBe('Rey Alvarez');
});
