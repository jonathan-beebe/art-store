<?php

declare(strict_types=1);

namespace App\Domain\Seller;

it('carries the facts through, alongside the conversation counts', function (): void {
    $facts = new CustomerTallyFacts(customers: 2, newThisPeriod: 1, repeatBuyers: 1, orders: 4, spentCents: 60000);

    $tally = CustomerTally::of($facts, openConversations: 5, unreadConversations: 2);

    expect($tally->customers)->toBe(2)
        ->and($tally->newThisPeriod)->toBe(1)
        ->and($tally->repeatBuyers)->toBe(1)
        ->and($tally->orders)->toBe(4)
        ->and($tally->spentCents)->toBe(60000)
        ->and($tally->openConversations)->toBe(5)
        ->and($tally->unreadConversations)->toBe(2);
});

it('reads the repeat share as whole percent', function (): void {
    $facts = new CustomerTallyFacts(customers: 3, newThisPeriod: 0, repeatBuyers: 2, orders: 5, spentCents: 30000);

    expect(CustomerTally::of($facts, 0, 0)->repeatShare())->toBe(67);
});

it('reads the average order as money', function (): void {
    $facts = new CustomerTallyFacts(customers: 2, newThisPeriod: 0, repeatBuyers: 0, orders: 4, spentCents: 60000);

    expect(CustomerTally::of($facts, 0, 0)->averageOrder())->toBeMoney(15000);
});

it('has no share and no average before there is a buyer', function (): void {
    $facts = new CustomerTallyFacts(customers: 0, newThisPeriod: 0, repeatBuyers: 0, orders: 0, spentCents: 0);

    $tally = CustomerTally::of($facts, 0, 0);

    expect($tally->customers)->toBe(0)
        ->and($tally->repeatShare())->toBeNull()
        ->and($tally->averageOrder())->toBeNull();
});
