<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Listings\RecordListingEvent;
use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use DomainException;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;

it('reads the faqs published on it', function (): void {
    $listing = $this->listing($this->seller());
    ListingFaq::factory()->create(['listing_id' => $listing->id]);
    ListingFaq::factory()->create(['listing_id' => $this->listing($this->seller())->id]);

    expect($listing->faqs()->count())->toBe(1);
});

it('surfaces only listings for sale on the storefront', function (): void {
    $seller = $this->seller();
    $forSale = $this->listing($seller);
    $this->listing($seller, ['status' => ListingStatus::Draft]);
    $this->listing($seller, ['status' => ListingStatus::Sold, 'quantity' => 0]);

    expect(Listing::query()->forSale()->pluck('id')->all())->toBe([$forSale->id]);
});

it('takes the rows placement reads for update, in id order', function (): void {
    // SQLite has no row lock and its grammar compiles the clause away, so the
    // query is compiled here with the grammar of a database that does have
    // one — what the same read asks for in production.
    $query = Listing::query()->lockedForPlacement()->toBase();

    expect((new MySqlGrammar(DB::connection()))->compileSelect($query))
        ->toContain('order by `id` asc')
        ->toEndWith('for update');
});

it('reads whether it can still be bought', function (): void {
    $seller = $this->seller();

    expect($this->listing($seller)->isPurchasable())->toBeTrue()
        ->and($this->listing($seller, ['status' => ListingStatus::Archived])->isPurchasable())->toBeFalse()
        ->and($this->listing($seller, ['status' => ListingStatus::ForSale, 'quantity' => 0])->isPurchasable())->toBeFalse();
});

it('counts its events by type, whether queried or already in hand', function (): void {
    $listing = $this->listing($this->seller());
    $recordListingEvent = app(RecordListingEvent::class);
    $recordListingEvent($listing, null, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));
    $recordListingEvent($listing, null, ListingEventType::View, $this->moment('2026-08-20 10:00:00'));
    $recordListingEvent($listing, null, ListingEventType::Favorite, $this->moment('2026-08-20 11:00:00'));

    $queried = Listing::query()->withEventCounts()->findOrFail($listing->id);
    $loaded = $listing->loadEventCounts();

    expect($queried->views_count)->toBe(2)
        ->and($loaded->views_count)->toBe(2)
        ->and($loaded->favorites_count)->toBe(1)
        ->and($loaded->cart_adds_count)->toBe(0);
});

it('reads its price as money', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 45000]);

    expect($listing->price()->format())->toBe('$450.00');
});

it('renders a placeholder image when there is no upload', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Blue Heron', 'image_path' => null]);

    expect($listing->imageUrl())->toStartWith('data:image/svg+xml;base64,');
});

it('serves an uploaded image from the public disk', function (): void {
    $listing = $this->listing($this->seller(), ['image_path' => 'listings/heron.png']);

    expect($listing->imageUrl())->toEndWith('/storage/listings/heron.png');
});

it('sells items off its quantity', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => 3]);

    $listing->sell(2);

    expect($listing->refresh()->quantity)->toBe(1)
        ->and($listing)->toHaveStatus(ListingStatus::ForSale);
});

it('is sold once the last item goes', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => 1]);

    $listing->sell(1);

    expect($listing->refresh()->quantity)->toBe(0)
        ->and($listing)->toHaveStatus(ListingStatus::Sold);
});

it('refuses a sale of more than it holds, and writes nothing', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => 1]);

    expect(fn () => $listing->sell(2))->toThrow(DomainException::class)
        ->and($listing->refresh()->quantity)->toBe(1);
});

it('refuses a sale of a listing that left the storefront', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Archived]);

    expect(fn () => $listing->sell(1))->toThrow(DomainException::class, 'is no longer for sale');
});

it('restocks items a sale took', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => 1]);
    $listing->sell(1);

    $listing->restock(1);

    expect($listing->refresh()->quantity)->toBe(1)
        ->and($listing)->toHaveStatus(ListingStatus::ForSale);
});

it('moves through an allowed status transition', function (ListingStatus $from, ListingStatus $to): void {
    $listing = $this->listing($this->seller(), ['status' => $from]);

    $listing->changeStatusTo($to);

    expect($listing)->toHaveStatus($to);
})->with([
    'draft to for sale' => [ListingStatus::Draft, ListingStatus::ForSale],
    'for sale to archived' => [ListingStatus::ForSale, ListingStatus::Archived],
]);

it('refuses a status transition the lifecycle does not allow, and leaves the row alone', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Draft]);

    expect(fn () => $listing->changeStatusTo(ListingStatus::Sold))->toThrow(DomainException::class)
        ->and($listing)->toHaveStatus(ListingStatus::Draft);
});

it('reads the order items it was bought through', function (): void {
    $listing = $this->listing($this->seller());
    $order = $this->orderFor($this->verifiedCustomer(), $listing);

    expect($listing->orderItems()->pluck('order_id')->all())->toBe([$order->id]);
});

it('reads the favorites it was added to', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->anonymousCustomer();
    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => $listing->id]);

    expect($listing->favorites()->pluck('customer_id')->all())->toBe([$customer->id]);
});
