<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Escrow\PayoutPeriod;
use App\Domain\Seller\CustomerRow;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Seller;
use Closure;
use DateTimeImmutable;

/**
 * An order mints its fulfillment row the moment it is placed, before a card
 * is even charged (docs/orders.md), so a checkout nobody paid for has to
 * read as though it never happened everywhere a seller's numbers come from.
 * One abandoned checkout, checked against every reader
 * {@see Fulfillment::counted()} backs.
 */
it('leaves an unpaid order out of every seller figure', function (Closure $countedBy): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($seller, ['price_cents' => 20000]));
    $order->update(['placed_at' => new DateTimeImmutable('2026-08-18 10:00:00')]);
    $fulfillment = $order->fulfillments()->sole();

    expect($countedBy($seller, $customer, $fulfillment))->toBeFalsy();
})->with([
    'the customers table' => fn (Seller $seller, Customer $customer): ?CustomerRow => SellerCustomers::forCustomer($seller, $customer),
    "the order page's customer card" => fn (Seller $seller, Customer $customer, Fulfillment $fulfillment): int => app(CustomerOnOrder::class)->facts($fulfillment)->orders,
    'the held escrow list' => fn (Seller $seller): array => HeldEscrow::for($seller)->orders,
    'the listings table' => fn (Seller $seller): int => ListingTable::forSeller($seller, AnalyticsRange::of(30, new DateTimeImmutable('2026-08-25')))[0]->sold,
    "a payout period's sales" => fn (Seller $seller): array => PeriodSales::for($seller, PayoutPeriod::containing(new DateTimeImmutable('2026-08-18 10:00:00'))),
    'the earnings window' => fn (Seller $seller): int => EarningsPeriods::for($seller, new DateTimeImmutable('2026-08-19 09:00:00'))->current()->orderCount,
]);
