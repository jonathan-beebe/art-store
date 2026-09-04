<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Orders\FinalizeOrder;
use App\Models\Customer;

it('forFulfillment carries the seller, the customer, the one fulfillment, and the seller\'s own listings on the order', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $other = $this->seller('Lovegood Curiosities');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $listing = $this->listing($seller, ['title' => 'The Burrow at Dusk']);
    $otherListing = $this->listing($other, ['title' => 'Nine Owls']);

    $order = $this->orderFor($customer, $listing, $otherListing);
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = $order->fulfillments()->where('seller_id', $seller->id)->sole();

    $scope = FeedScope::forFulfillment($fulfillment);

    expect($scope->sellerId)->toBe($seller->id)
        ->and($scope->customerId)->toBe($customer->id)
        ->and($scope->customerName)->toBe('Harry Potter')
        ->and($scope->fulfillmentIds)->toBe([$fulfillment->id])
        ->and($scope->listingIds)->toBe([$listing->id])
        ->and($scope->isOneOrder)->toBeTrue();
});

it('forCustomer carries every fulfillment of the pair and every listing the seller has', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Hermione Granger']);
    $otherCustomer = Customer::factory()->create(['name' => 'Cho Chang']);

    $fulfillmentOne = $this->paidFulfillmentFor($seller, $customer, 5000);
    $fulfillmentTwo = $this->paidFulfillmentFor($seller, $customer, 3000);
    $this->paidFulfillmentFor($seller, $otherCustomer, 4000);
    $extraListing = $this->listing($seller, ['title' => 'Copper Cauldron Bowl']);

    $scope = FeedScope::forCustomer($seller, $customer);

    expect($scope->sellerId)->toBe($seller->id)
        ->and($scope->customerId)->toBe($customer->id)
        ->and($scope->customerName)->toBe('Hermione Granger')
        ->and($scope->isOneOrder)->toBeFalse()
        ->and($scope->fulfillmentIds)->toEqualCanonicalizing([$fulfillmentOne->id, $fulfillmentTwo->id]);

    expect($scope->listingIds)->toEqualCanonicalizing($seller->listings()->pluck('id')->all())
        ->and($scope->listingIds)->toContain($extraListing->id);
});
