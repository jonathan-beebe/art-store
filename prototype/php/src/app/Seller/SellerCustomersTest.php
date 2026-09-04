<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Fulfillment\RefundFulfillment;
use App\Domain\Seller\CustomerRow;
use App\Domain\Seller\CustomerSegment;
use App\Domain\Seller\CustomerSortColumn;
use App\Domain\Seller\SortDirection;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Message;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

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

it('names many buyers holding no account in the same number of queries as one', function (): void {
    $seller = $this->seller('The Burrow Craftworks');

    $addAnonymousBuyer = function () use ($seller): void {
        $this->paidFulfillmentFor($seller, $this->anonymousCustomer());
    };

    $queriesForList = function () use ($seller): int {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        SellerCustomers::forSeller($seller);

        return $queries;
    };

    $addAnonymousBuyer();
    $withOne = $queriesForList();

    $addAnonymousBuyer();
    $addAnonymousBuyer();
    $addAnonymousBuyer();
    $addAnonymousBuyer();
    $withFive = $queriesForList();

    expect(SellerCustomers::forSeller($seller))->toHaveCount(5)
        ->and($withFive)->toBe($withOne);
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

it('leaves out an order that was placed and never paid for', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Cho Chang']);

    $this->orderFor($customer, $this->listing($seller, ['price_cents' => 5000]));

    expect(SellerCustomers::forSeller($seller))->toBe([])
        ->and(SellerCustomers::forCustomer($seller, $customer))->toBeNull();
});

it('counts the parcels that still stand and drops the ones that settled back', function (string $settle): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Neville Longbottom']);

    $this->paidFulfillmentFor($seller, $customer, 5000);
    $settled = $this->paidFulfillmentFor($seller, $customer, 9000);

    $settle === 'declined'
        ? app(DeclineFulfillment::class)($settled, 'The kiln cracked it.', $this->moment('2026-08-21 09:00:00'))
        : app(RefundFulfillment::class)($settled, $this->admin(), 'It arrived chipped.', $this->moment('2026-08-21 09:00:00'));

    $row = SellerCustomers::forCustomer($seller, $customer);

    expect($row?->orders)->toBe(1)
        ->and($row?->spentCents)->toBe(5000);
})->with(['declined', 'refunded']);

it('IMPRV-038 sorts and pages a segment entirely in the query', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    foreach (range(1, 12) as $i) {
        $this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => "Buyer {$i}"]), $i * 1000);
    }

    $newSince = new DateTimeImmutable('2000-01-01 00:00:00');

    $firstPage = SellerCustomers::pageForSeller($seller, CustomerSegment::All, $newSince, CustomerSortColumn::Spent, SortDirection::Desc, limit: 5, offset: 0);
    $secondPage = SellerCustomers::pageForSeller($seller, CustomerSegment::All, $newSince, CustomerSortColumn::Spent, SortDirection::Desc, limit: 5, offset: 5);

    expect(array_map(fn (CustomerRow $row): int => $row->spentCents, $firstPage))->toBe([12000, 11000, 10000, 9000, 8000])
        ->and(array_map(fn (CustomerRow $row): int => $row->spentCents, $secondPage))->toBe([7000, 6000, 5000, 4000, 3000]);
});

it('IMPRV-038 sorts a page by any column entirely in the query', function (CustomerSortColumn $column, callable $expectedOrder): void {
    $seller = $this->seller('The Burrow Craftworks');

    $ginny = Customer::factory()->create(['name' => 'Ginny Weasley']);
    $cho = Customer::factory()->create(['name' => 'Cho Chang']);
    $ada = Customer::factory()->create(['name' => 'Ada Bell']);

    $this->paidFulfillmentFor($seller, $ginny, 3000)->order->update(['placed_at' => now()->subDays(10)]);

    $this->paidFulfillmentFor($seller, $cho, 500)->order->update(['placed_at' => now()->subDays(20)]);
    $this->paidFulfillmentFor($seller, $cho, 500)->order->update(['placed_at' => now()->subDays(8)]);

    $this->paidFulfillmentFor($seller, $ada, 2000)->order->update(['placed_at' => now()->subDays(30)]);
    $this->paidFulfillmentFor($seller, $ada, 2000)->order->update(['placed_at' => now()->subDays(15)]);
    $this->paidFulfillmentFor($seller, $ada, 2000)->order->update(['placed_at' => now()->subDays(5)]);

    // Favorites, ascending: ada (1) < ginny (2) < cho (3).
    Favorite::factory()->create(['customer_id' => $ginny->id, 'listing_id' => $this->listing($seller)->id]);
    Favorite::factory()->create(['customer_id' => $ginny->id, 'listing_id' => $this->listing($seller)->id]);
    Favorite::factory()->create(['customer_id' => $cho->id, 'listing_id' => $this->listing($seller)->id]);
    Favorite::factory()->create(['customer_id' => $cho->id, 'listing_id' => $this->listing($seller)->id]);
    Favorite::factory()->create(['customer_id' => $cho->id, 'listing_id' => $this->listing($seller)->id]);
    Favorite::factory()->create(['customer_id' => $ada->id, 'listing_id' => $this->listing($seller)->id]);

    // Conversations, ascending: cho (0) < ginny (1) < ada (2).
    Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $ginny->id]);
    Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $ada->id]);
    Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $ada->id]);

    $ids = ['ginny' => $ginny->id, 'cho' => $cho->id, 'ada' => $ada->id];

    $page = SellerCustomers::pageForSeller($seller, CustomerSegment::All, new DateTimeImmutable('2000-01-01 00:00:00'), $column, SortDirection::Asc, limit: 50, offset: 0);

    expect(array_map(fn (CustomerRow $row): string => $row->customerId, $page))->toBe($expectedOrder($ids));
})->with([
    // Orders: ginny 1, cho 2, ada 3.
    'orders' => [CustomerSortColumn::Orders, fn (array $ids): array => [$ids['ginny'], $ids['cho'], $ids['ada']]],
    // Spent: cho 1000, ginny 3000, ada 6000.
    'spent' => [CustomerSortColumn::Spent, fn (array $ids): array => [$ids['cho'], $ids['ginny'], $ids['ada']]],
    'favorites' => [CustomerSortColumn::Favorites, fn (array $ids): array => [$ids['ada'], $ids['ginny'], $ids['cho']]],
    'conversations' => [CustomerSortColumn::Conversations, fn (array $ids): array => [$ids['cho'], $ids['ginny'], $ids['ada']]],
    // Last order: ginny -10d, cho -8d, ada -5d.
    'last_order' => [CustomerSortColumn::LastOrder, fn (array $ids): array => [$ids['ginny'], $ids['cho'], $ids['ada']]],
    // Since (first order): ada -30d, cho -20d, ginny -10d.
    'since' => [CustomerSortColumn::Since, fn (array $ids): array => [$ids['ada'], $ids['cho'], $ids['ginny']]],
    // Name: Ada Bell, Cho Chang, Ginny Weasley.
    'name' => [CustomerSortColumn::Name, fn (array $ids): array => [$ids['ada'], $ids['cho'], $ids['ginny']]],
]);

it('IMPRV-038 sorts by name across an account-named and an order-named buyer, both directions', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $accountNamed = Customer::factory()->create(['name' => 'Bellatrix Lestrange']);
    $orderNamed = $this->anonymousCustomer();

    $this->paidFulfillmentFor($seller, $accountNamed, 1000);
    $this->paidFulfillmentFor($seller, $orderNamed, 1000)->order->update(['shipping_name' => 'Albus Dumbledore']);

    $newSince = new DateTimeImmutable('2000-01-01 00:00:00');

    $ascending = SellerCustomers::pageForSeller($seller, CustomerSegment::All, $newSince, CustomerSortColumn::Name, SortDirection::Asc, limit: 50, offset: 0);
    $descending = SellerCustomers::pageForSeller($seller, CustomerSegment::All, $newSince, CustomerSortColumn::Name, SortDirection::Desc, limit: 50, offset: 0);

    expect(array_map(fn (CustomerRow $row): string => $row->name, $ascending))->toBe(['Albus Dumbledore', 'Bellatrix Lestrange'])
        ->and(array_map(fn (CustomerRow $row): string => $row->name, $descending))->toBe(['Bellatrix Lestrange', 'Albus Dumbledore']);
});

it('IMPRV-038 breaks a tie on id ascending whichever way the column sorts', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $first = $this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Cho Chang']), 5000);
    $second = $this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Ginny Weasley']), 5000);
    $ids = [$first->customer_id, $second->customer_id];
    sort($ids);

    $newSince = new DateTimeImmutable('2000-01-01 00:00:00');

    $ascending = SellerCustomers::pageForSeller($seller, CustomerSegment::All, $newSince, CustomerSortColumn::Spent, SortDirection::Asc, limit: 10, offset: 0);
    $descending = SellerCustomers::pageForSeller($seller, CustomerSegment::All, $newSince, CustomerSortColumn::Spent, SortDirection::Desc, limit: 10, offset: 0);

    expect(array_map(fn (CustomerRow $row): string => $row->customerId, $ascending))->toBe($ids)
        ->and(array_map(fn (CustomerRow $row): string => $row->customerId, $descending))->toBe($ids);
});

it('IMPRV-038 counts and pages a segment together, over the same HAVING clause', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $once = Customer::factory()->create(['name' => 'Cho Chang']);
    $twice = Customer::factory()->create(['name' => 'Ginny Weasley']);
    $this->paidFulfillmentFor($seller, $once);
    $this->paidFulfillmentFor($seller, $twice);
    $this->paidFulfillmentFor($seller, $twice);

    $newSince = new DateTimeImmutable('2000-01-01 00:00:00');

    $count = SellerCustomers::countForSegment($seller, CustomerSegment::Repeat, $newSince);
    $page = SellerCustomers::pageForSeller($seller, CustomerSegment::Repeat, $newSince, CustomerSortColumn::default(), SortDirection::Desc, limit: 50, offset: 0);

    expect($count)->toBe(1)
        ->and($page)->toHaveCount(1)
        ->and($page[0]->customerId)->toBe($twice->id);
});

it('IMPRV-038 counts only buyers new since the window on a page', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $old = Customer::factory()->create(['name' => 'Cho Chang']);
    $new = Customer::factory()->create(['name' => 'Ginny Weasley']);
    $this->paidFulfillmentFor($seller, $old)->order->update(['placed_at' => now()->subDays(120)]);
    $this->paidFulfillmentFor($seller, $new)->order->update(['placed_at' => now()->subDay()]);

    $newSince = new DateTimeImmutable((string) now()->subDays(30));

    $page = SellerCustomers::pageForSeller($seller, CustomerSegment::New, $newSince, CustomerSortColumn::default(), SortDirection::Desc, limit: 50, offset: 0);

    expect($page)->toHaveCount(1)->and($page[0]->customerId)->toBe($new->id);
});

it('IMPRV-038 resolves a paged buyer\'s name, favorites, and conversations the same as the unpaged read', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = $this->anonymousCustomer();
    $fulfillment = $this->paidFulfillmentFor($seller, $customer, 5000);
    $fulfillment->order->update(['shipping_name' => 'Nymphadora Tonks', 'email' => 'tonks@example.test']);

    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => $this->listing($seller)->id]);
    Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);

    $newSince = new DateTimeImmutable('2000-01-01 00:00:00');
    $page = SellerCustomers::pageForSeller($seller, CustomerSegment::All, $newSince, CustomerSortColumn::default(), SortDirection::Desc, limit: 50, offset: 0);

    expect($page)->toHaveCount(1);
    expect($page[0]->name)->toBe('Nymphadora Tonks')
        ->and($page[0]->email)->toBe('tonks@example.test')
        ->and($page[0]->favorites)->toBe(1)
        ->and($page[0]->conversations)->toBe(1);
});

it('IMPRV-038 pages a page of rows in a fixed number of queries however many buyers there are', function (): void {
    $seller = $this->seller('The Burrow Craftworks');

    $queriesForAPage = function () use ($seller): int {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        SellerCustomers::pageForSeller($seller, CustomerSegment::All, new DateTimeImmutable('2000-01-01 00:00:00'), CustomerSortColumn::default(), SortDirection::Desc, limit: 50, offset: 0);

        return $queries;
    };

    $this->paidFulfillmentFor($seller, Customer::factory()->create());
    $withOne = $queriesForAPage();

    foreach (range(1, 4) as $i) {
        $this->paidFulfillmentFor($seller, Customer::factory()->create());
    }
    $withFive = $queriesForAPage();

    expect($withFive)->toBe($withOne);
});

it('IMPRV-038 tallies every buyer to five figures in one query', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $once = Customer::factory()->create();
    $twice = Customer::factory()->create();
    $this->paidFulfillmentFor($seller, $once, 5000)->order->update(['placed_at' => now()->subDays(120)]);
    $this->paidFulfillmentFor($seller, $twice, 3000)->order->update(['placed_at' => now()->subDays(10)]);
    $this->paidFulfillmentFor($seller, $twice, 3000)->order->update(['placed_at' => now()->subDays(5)]);

    $facts = SellerCustomers::tallyFor($seller, new DateTimeImmutable((string) now()->subDays(30)));

    expect($facts->customers)->toBe(2)
        ->and($facts->newThisPeriod)->toBe(1)
        ->and($facts->repeatBuyers)->toBe(1)
        ->and($facts->orders)->toBe(3)
        ->and($facts->spentCents)->toBe(11000);
});

it('IMPRV-038 tallies zero buyers without error', function (): void {
    $seller = $this->seller('The Burrow Craftworks');

    $facts = SellerCustomers::tallyFor($seller, new DateTimeImmutable('2000-01-01 00:00:00'));

    expect($facts->customers)->toBe(0)
        ->and($facts->newThisPeriod)->toBe(0)
        ->and($facts->repeatBuyers)->toBe(0)
        ->and($facts->orders)->toBe(0)
        ->and($facts->spentCents)->toBe(0);
});

it('IMPRV-038 tallies every buyer in one query, however many there are', function (): void {
    $seller = $this->seller('The Burrow Craftworks');

    $queriesForTally = function () use ($seller): int {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        SellerCustomers::tallyFor($seller, new DateTimeImmutable('2000-01-01 00:00:00'));

        return $queries;
    };

    $this->paidFulfillmentFor($seller, Customer::factory()->create());
    $withOne = $queriesForTally();

    foreach (range(1, 4) as $i) {
        $this->paidFulfillmentFor($seller, Customer::factory()->create());
    }
    $withFive = $queriesForTally();

    expect($withOne)->toBe(1)->and($withFive)->toBe($withOne);
});
