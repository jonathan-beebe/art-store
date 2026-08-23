<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Listings\ListingStatus;

it('resolves a display name', function (array $attributes, string $expected): void {
    /** @var array<string, mixed> $attributes */
    expect((new Seller($attributes))->displayName())->toBe($expected);
})->with([
    'shop name wins' => [['email' => 'artist@example.com', 'name' => 'Ada Painter', 'shop_name' => 'Ada Studio'], 'Ada Studio'],
    'name without a shop name' => [['email' => 'artist@example.com', 'name' => 'Ada Painter'], 'Ada Painter'],
    'email alone' => [['email' => 'artist@example.com'], 'artist@example.com'],
]);

it('is named by the morph alias its notifications are addressed to', function (): void {
    expect((new Seller)->getMorphClass())->toBe('seller');
});

it('reads the conversations it is a participant in', function (): void {
    $seller = $this->seller();
    Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);
    Conversation::factory()->listingQuestion()->create();

    expect($seller->conversations()->count())->toBe(1);
});

it('reads the messages it sent', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);
    Message::factory()->from($seller)->create(['conversation_id' => $conversation->id]);
    Message::factory()->create(['conversation_id' => $conversation->id]);

    expect($seller->sentMessages()->count())->toBe(1);
});

it('counts its listings by status without loading one', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['status' => ListingStatus::Draft]);
    $this->listing($seller, ['status' => ListingStatus::ForSale]);
    $this->listing($seller, ['status' => ListingStatus::ForSale]);
    $this->listing($this->seller('Other Studio'), ['status' => ListingStatus::ForSale]);

    $this->expectsDatabaseQueryCount(1);

    expect($seller->listingCountsByStatus())->toBe([
        ListingStatus::Draft->value => 1,
        ListingStatus::ForSale->value => 2,
    ]);
});

it('reads its escrow balance out of one grouped query', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor($seller, priceCents: 10000, trackingNumber: 'RM1');
    $this->deliveredFulfillmentFor($seller, priceCents: 20000, trackingNumber: 'RM2');
    $this->shippedFulfillmentFor($seller, priceCents: 30000, trackingNumber: 'RM3');

    $this->expectsDatabaseQueryCount(1);
    $balance = $seller->escrowBalance();

    expect($balance->available)->toBeMoney(27000)
        ->and($balance->held)->toBeMoney(27000);
});
