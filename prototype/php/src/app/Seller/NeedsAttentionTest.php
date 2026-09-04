<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Messaging\OpenConversation;
use App\Actions\Messaging\PostMessage;
use App\Domain\Listings\ListingStatus;
use App\Domain\Messaging\MessageBody;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\Messaging\ThreadTitle;
use App\Domain\Seller\AttentionGroup;
use App\Models\Customer;
use App\Models\Seller;
use DateTimeImmutable;

function attentionNow(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-09-04 12:00:00');
}

/**
 * @return list<AttentionGroup>
 */
function attentionFor(Seller $seller): array
{
    $now = attentionNow();

    return NeedsAttention::for($seller, NextPayout::for($seller, $now)->estimate, $now);
}

it('builds the four groups the dashboard renders', function (): void {
    $groups = attentionFor($this->seller('The Burrow Craftworks'));

    expect($groups)->toHaveCount(4)
        ->and(array_map(fn (AttentionGroup $group): string => $group->actionLabel, $groups))
        ->toBe(['Open orders', 'Open messages', 'See earnings', 'Open listings']);
});

it('reads the parcels waiting to ship oldest first, with the age in the meta', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $first = $this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Harry Potter']));
    $this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Ginny Weasley']));

    $orders = attentionFor($seller)[0];

    expect($orders->title)->toBe('2 orders to ship')
        ->and($orders->rows[0]->href)->toBe(route('seller.orders.show', ['fulfillment' => $first->id, 'lane' => 'ship']))
        ->and($orders->rows[0]->meta)->toBe('15 days ago')
        ->and($orders->rows[0]->urgent)->toBeTrue();
});

it('leaves a parcel inside the two-day window unmarked', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->paidFulfillmentFor($seller);

    $now = new DateTimeImmutable('2026-08-21 12:00:00');
    $groups = NeedsAttention::for($seller, NextPayout::for($seller, $now)->estimate, $now);

    expect($groups[0]->rows[0]->urgent)->toBeFalse();
});

it('leaves a shipped parcel out of the ship queue', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->shippedFulfillmentFor($seller);

    expect(attentionFor($seller)[0]->title)->toBe('No orders to ship')
        ->and(attentionFor($seller)[0]->rows)->toBe([]);
});

it('shows the head of a long ship queue and counts the whole of it', function (): void {
    $seller = $this->seller('The Burrow Craftworks');

    foreach (range(1, 7) as $number) {
        $this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => "Buyer {$number}"]));
    }

    $orders = attentionFor($seller)[0];

    expect($orders->title)->toBe('7 orders to ship')
        ->and($orders->rows)->toHaveCount(5)
        ->and($orders->hidden())->toBe(2);
});

it('reads a buyer thread the seller has not opened, quoting the message', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $listing = $this->listing($seller);

    $thread = app(OpenConversation::class)(
        ThreadOpening::listingQuestion($seller->id, $customer->id, $listing->id, ThreadTitle::of('Does it hold hot tea?')),
        new DateTimeImmutable('2026-09-03 09:00:00'),
    );
    app(PostMessage::class)($thread, $customer, MessageBody::of('Does it hold hot tea without cracking?'), new DateTimeImmutable('2026-09-03 09:00:00'));

    $messages = attentionFor($seller)[1];

    expect($messages->title)->toBe('1 message waiting on you')
        ->and($messages->rows[0]->initials)->toBe('HP')
        ->and($messages->rows[0]->title)->toBe('Harry Potter · Does it hold hot tea?')
        ->and($messages->rows[0]->supporting)->toBe('Does it hold hot tea without cracking?')
        ->and($messages->rows[0]->href)->toBe(route('seller.messages.show', ['conversation' => $thread->id]));
});

it('leaves a thread the seller wrote last out of the waiting queue', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $listing = $this->listing($seller);

    $thread = app(OpenConversation::class)(
        ThreadOpening::listingQuestion($seller->id, $customer->id, $listing->id, ThreadTitle::of('Does it hold hot tea?')),
        new DateTimeImmutable('2026-09-03 09:00:00'),
    );
    app(PostMessage::class)($thread, $seller, MessageBody::of('It does, and it keeps it warm.'), new DateTimeImmutable('2026-09-03 10:00:00'));

    expect(attentionFor($seller)[1]->title)->toBe('No messages waiting on you');
});

it('splits the money into what has released and what is still held', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->deliveredFulfillmentFor($seller, priceCents: 10000);
    $this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Harry Potter']), priceCents: 20000);

    $payout = attentionFor($seller)[2];

    expect($payout->title)->toStartWith('Payout ')
        ->and($payout->rows)->toHaveCount(2)
        ->and($payout->rows[0]->title)->toBe('$90.00 released and ready')
        ->and($payout->rows[0]->supporting)->toBe('1 delivered order since the last payout')
        ->and($payout->rows[1]->title)->toBe('$180.00 still held')
        ->and($payout->rows[1]->supporting)->toBe('1 order waiting on delivery')
        ->and($payout->rows[1]->href)->toBe(route('seller.orders.index', ['lane' => 'progress']));
});

it('lists the drafts and the sold-out pieces, most recently edited first', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $draft = $this->listing($seller, ['title' => 'Patchwork Shawl Runner', 'status' => ListingStatus::Draft]);
    $sold = $this->listing($seller, ['title' => 'Copper Cauldron Bowl', 'status' => ListingStatus::Sold]);
    $this->listing($seller, ['title' => 'Nine Owls', 'status' => ListingStatus::ForSale]);
    $this->listing($seller, ['title' => 'Tea Leaf Study', 'status' => ListingStatus::Archived]);

    $listings = attentionFor($seller)[3];
    $titles = array_map(fn ($row): string => $row->title, $listings->rows);

    sort($titles);

    expect($listings->title)->toBe('2 listings need work')
        ->and($titles)->toBe(['Copper Cauldron Bowl', 'Patchwork Shawl Runner'])
        ->and(array_map(fn ($row): string => $row->href, $listings->rows))
        ->toContain(route('seller.listings.show', ['listing' => $draft->id]), route('seller.listings.show', ['listing' => $sold->id]));
});

it('says what is in a listings way in the supporting line', function (ListingStatus $status, string $supporting): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->listing($seller, ['title' => 'Nine Owls', 'status' => $status]);

    expect(attentionFor($seller)[3]->rows[0]->supporting)->toBe($supporting);
})->with([
    'a draft' => [ListingStatus::Draft, 'Draft · not on the storefront yet'],
    'a sold-out piece' => [ListingStatus::Sold, 'Sold out · restock it or archive it'],
]);

it('leaves another sellers work off every queue', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $other = $this->seller('Ollivanders');
    $this->paidFulfillmentFor($other);
    $this->listing($other, ['status' => ListingStatus::Draft]);

    $groups = attentionFor($seller);

    expect($groups[0]->title)->toBe('No orders to ship')
        ->and($groups[3]->title)->toBe('No listings need work');
});

it('links each group header at the tool that clears it', function (): void {
    $groups = attentionFor($this->seller('The Burrow Craftworks'));

    expect(array_map(fn (AttentionGroup $group): string => $group->actionHref, $groups))->toBe([
        route('seller.orders.index', ['lane' => 'ship']),
        route('seller.messages.index'),
        route('seller.earnings'),
        route('seller.listings.index'),
    ]);
});
