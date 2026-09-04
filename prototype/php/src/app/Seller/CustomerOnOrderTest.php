<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Fulfillment\DeclineFulfillment;

it('reads the buyer off the order that carries them', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 45000);

    $facts = app(CustomerOnOrder::class)->facts($fulfillment);

    expect($facts->name)->toBe('Ada Lovelace')
        ->and($facts->orders)->toBe(1)
        ->and($facts->spend)->toBeMoney(45000)
        ->and($facts->since?->format('Y-m-d'))->toBe('2026-08-20');
});

it('counts every parcel this buyer had from this seller', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $this->paidFulfillmentFor($seller, $customer, priceCents: 10000);
    $second = $this->paidFulfillmentFor($seller, $customer, priceCents: 20000);

    $facts = app(CustomerOnOrder::class)->facts($second);

    expect($facts->orders)->toBe(2)
        ->and($facts->spend)->toBeMoney(30000);
});

it('leaves a parcel that was turned down out of the count and the spend', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $kept = $this->paidFulfillmentFor($seller, $customer, priceCents: 10000);
    $declined = $this->paidFulfillmentFor($seller, $customer, priceCents: 20000);
    app(DeclineFulfillment::class)($declined, 'The kiln cracked the glaze.', $this->moment('2026-08-21 09:00:00'));

    $facts = app(CustomerOnOrder::class)->facts($kept);

    expect($facts->orders)->toBe(1)
        ->and($facts->spend)->toBeMoney(10000);
});

it('counts nothing of what this buyer bought from another seller', function (): void {
    $mine = $this->seller('Blue Kiln Studio');
    $customer = $this->verifiedCustomer();
    $fulfillment = $this->paidFulfillmentFor($mine, $customer, priceCents: 10000);
    $this->paidFulfillmentFor($this->seller('Rye Press'), $customer, priceCents: 90000);

    expect(app(CustomerOnOrder::class)->facts($fulfillment)->spend)->toBeMoney(10000);
});

it('says since when nothing while every parcel of theirs went back', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 10000);
    app(DeclineFulfillment::class)($fulfillment, 'The kiln cracked the glaze.', $this->moment('2026-08-21 09:00:00'));

    $facts = app(CustomerOnOrder::class)->facts($fulfillment->refresh());

    expect($facts->orders)->toBe(0)
        ->and($facts->spend)->toBeMoney(0)
        ->and($facts->since)->toBeNull();
});
