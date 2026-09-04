<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Fulfillment\DeclineFulfillment;
use App\Domain\Seller\CustomerRow;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Message;

it('reads a buyer as their orders, spend, favorites, conversations, and first and last order', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Luna Lovegood', 'email' => 'luna@example.test']);

    $first = $this->paidFulfillmentFor($seller, $customer, 5000);
    $first->order->update(['placed_at' => $this->moment('2026-06-01 09:00:00')]);
    $second = $this->paidFulfillmentFor($seller, $customer, 3000);
    $second->order->update(['placed_at' => $this->moment('2026-08-14 09:00:00')]);

    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => $this->listing($seller)->id]);
    Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);

    $rows = SellerCustomers::forSeller($seller);

    expect($rows)->toHaveCount(1);
    expect($rows[0]->customerId)->toBe($customer->id)
        ->and($rows[0]->name)->toBe('Luna Lovegood')
        ->and($rows[0]->email)->toBe('luna@example.test')
        ->and($rows[0]->orders)->toBe(2)
        ->and($rows[0]->spentCents)->toBe(8000)
        ->and($rows[0]->favorites)->toBe(1)
        ->and($rows[0]->conversations)->toBe(1)
        ->and($rows[0]->firstOrderAt->format('Y-m-d'))->toBe('2026-06-01')
        ->and($rows[0]->lastOrderAt->format('Y-m-d'))->toBe('2026-08-14');
});

it('leaves out someone who has only browsed, favorited, and asked', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $listing = $this->listing($seller);
    $visitor = Customer::factory()->create(['name' => 'Draco Malfoy']);

    Favorite::factory()->create(['customer_id' => $visitor->id, 'listing_id' => $listing->id]);
    Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $visitor->id,
        'listing_id' => $listing->id,
    ]);

    expect(SellerCustomers::forSeller($seller))->toBe([])
        ->and(SellerCustomers::forCustomer($seller, $visitor))->toBeNull();
});

it('leaves out a buyer whose only parcel was declined', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Cho Chang']);
    $fulfillment = $this->paidFulfillmentFor($seller, $customer);

    app(DeclineFulfillment::class)($fulfillment, 'The kiln cracked it.', $this->moment('2026-08-21 09:00:00'));

    expect(SellerCustomers::forSeller($seller))->toBe([]);
});

it('counts the seller\'s own favorites and conversations, never another seller\'s', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $other = $this->seller('Lovegood Curiosities');
    $customer = Customer::factory()->create(['name' => 'Ginny Weasley']);
    $this->paidFulfillmentFor($seller, $customer);

    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => $this->listing($seller)->id]);
    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => $this->listing($other)->id]);
    Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    Conversation::factory()->listingQuestion()->create(['seller_id' => $other->id, 'customer_id' => $customer->id]);

    $row = SellerCustomers::forCustomer($seller, $customer);

    expect($row)->toBeInstanceOf(CustomerRow::class)
        ->and($row?->favorites)->toBe(1)
        ->and($row?->conversations)->toBe(1);
});

it('counts one buyer\'s parcels alone', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Neville Longbottom']);
    $otherCustomer = Customer::factory()->create(['name' => 'Seamus Finnigan']);

    $this->paidFulfillmentFor($seller, $customer, 5000);
    $this->paidFulfillmentFor($seller, $otherCustomer, 9000);

    expect(SellerCustomers::forSeller($seller))->toHaveCount(2)
        ->and(SellerCustomers::forCustomer($seller, $customer)?->spentCents)->toBe(5000);
});

it('names a buyer holding no account from the latest order that carried a name', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = $this->anonymousCustomer();

    $first = $this->paidFulfillmentFor($seller, $customer, 5000);
    $first->order->update([
        'placed_at' => $this->moment('2026-06-01 09:00:00'),
        'shipping_name' => 'Nymphadora Tonks',
        'email' => 'tonks@example.test',
    ]);
    $latest = $this->paidFulfillmentFor($seller, $customer, 3000);
    $latest->order->update([
        'placed_at' => $this->moment('2026-08-14 09:00:00'),
        'shipping_name' => 'Nymphadora Lupin',
        'email' => 'lupin@example.test',
    ]);

    $row = SellerCustomers::forCustomer($seller, $customer);

    expect($row?->name)->toBe('Nymphadora Lupin')
        ->and($row?->email)->toBe('lupin@example.test');
});

it('counts a seller\'s open buyer threads and the ones holding an unread message', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);

    $unread = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    Message::factory()->from($customer)->create(['conversation_id' => $unread->id, 'sent_at' => $this->moment('2026-08-20 09:00:00')]);

    $read = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    Message::factory()->from($customer)->create([
        'conversation_id' => $read->id,
        'sent_at' => $this->moment('2026-08-20 09:00:00'),
        'read_at' => $this->moment('2026-08-20 10:00:00'),
    ]);

    Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'resolved_at' => $this->moment('2026-08-21 09:00:00'),
    ]);

    $counts = SellerCustomers::conversationCounts($seller);

    expect($counts->open)->toBe(2)
        ->and($counts->unread)->toBe(1);
});
