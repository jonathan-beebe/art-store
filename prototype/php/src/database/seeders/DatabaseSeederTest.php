<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\ListingEvent;
use App\Models\ListingFaq;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Property;
use App\Models\Seller;
use App\Support\Configurator\ListingHighlights;
use Illuminate\Notifications\DatabaseNotification;
use RuntimeException;
use Tests\CapturedStory;

// The seeders run once per test, in the hook, and write rows that only fit an
// empty schema once. Holding the capture of that one run is what lets a test
// read the story it told.
$seedRun = new class
{
    public ?CapturedStory $log = null;
};

beforeEach(function () use ($seedRun): void {
    $seedRun->log = CapturedStory::capture();
    $this->seed();
});

it('seeds seven verified sellers', function (): void {
    expect(Seller::count())->toBe(7)
        ->and(Seller::whereNotNull('email_verified_at')->count())->toBe(7);
});

it('seeds listings across statuses', function (): void {
    expect(Listing::where('status', ListingStatus::ForSale)->count())->toBe(41)
        ->and(Listing::where('status', ListingStatus::Draft)->count())->toBe(3)
        ->and(Listing::where('status', ListingStatus::Sold)->count())->toBe(2);
});

it('mirrors listing_attributes to the storefront media vocabulary, every listing categorized and attributed', function (): void {
    $medium = Property::where('name', 'Medium')->sole();

    expect(Listing::whereDoesntHave('listingAttributes', fn ($q) => $q->where('property_id', $medium->id))->count())->toBe(0)
        ->and(Listing::whereNull('category_id')->count())->toBe(0);

    $labels = ListingAttribute::where('property_id', $medium->id)
        ->with('propertyValue')
        ->get()
        ->map(fn (ListingAttribute $attribute): string => strtolower($attribute->propertyValue->label))
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($labels)->toBe([
        'apparel', 'ceramic', 'curio', 'jewelry', 'metal', 'painting', 'paper',
        'photograph', 'plant', 'print', 'publication', 'sculpture', 'textile', 'wood',
    ]);
});

it('gives the garden gnome a fixed Wood Species attribute beside its Medium — the no-choice case', function (): void {
    $gnome = Listing::where('title', 'Garden Gnome in Reclaimed Oak')->sole();
    $woodSpecies = Property::where('name', 'Wood Species')->sole();

    $attribute = $gnome->listingAttributes()
        ->with('propertyValue')
        ->where('property_id', $woodSpecies->id)
        ->sole();

    expect($attribute->propertyValue->label)->toBe('Oak')
        ->and(ListingHighlights::forStorefront($gnome))->toContain(['name' => 'Wood Species', 'values' => ['Oak']]);
});

it('seeds each listing through CreateListing, so every slug is a plain collision-free slug', function (): void {
    $listing = Listing::where('title', 'The Burrow at Dusk')->firstOrFail();

    expect($listing->slug)->toBe('the-burrow-at-dusk')
        ->and(Listing::query()->pluck('slug')->unique())->toHaveCount(Listing::count());
});

it('seeds the two sold-out listings by title, reached by selling out real stock', function (): void {
    $bowl = Listing::where('title', 'Copper Cauldron Bowl')->firstOrFail();
    $portrait = Listing::where('title', 'Wet Plate Portrait, Nearly Headless Gentleman')->firstOrFail();

    expect($bowl->status)->toBe(ListingStatus::Sold)
        ->and($bowl->quantity)->toBe(0)
        ->and($portrait->status)->toBe(ListingStatus::Sold)
        ->and($portrait->quantity)->toBe(0);
});

it('seeds the three draft listings by title', function (): void {
    foreach (['Quidditch Keeper, Charcoal Study', 'Tasseled Shawl Sampler', 'Glaze Test Tiles, Series 3'] as $title) {
        expect(Listing::where('title', $title)->firstOrFail()->status)->toBe(ListingStatus::Draft);
    }
});

it('seeds one verified customer with favorites', function (): void {
    $customer = Customer::where('email', 'hermione@example.com')->sole();

    expect($customer->email_verified_at)->not->toBeNull();
    expect(Favorite::where('customer_id', $customer->id)->count())->toBe(3);
    expect(ListingEvent::count())->toBeGreaterThanOrEqual(6);
});

it('seeds order history for two sellers', function (): void {
    expect(Order::count())->toBe(3)
        ->and(Order::where('status', '!=', OrderStatus::PendingVerification)->count())->toBe(3);

    expect(Fulfillment::where('status', FulfillmentStatus::AwaitingShipment)->count())->toBe(1)
        ->and(Fulfillment::where('status', FulfillmentStatus::Shipped)->count())->toBe(1)
        ->and(Fulfillment::where('status', FulfillmentStatus::Delivered)->count())->toBe(1);

    expect(Fulfillment::query()->distinct()->pluck('seller_id'))->toHaveCount(2);

    expect(Payment::count())->toBe(3);

    expect(OrderItem::query()->pluck('title')->sort()->values()->all())->toBe([
        'Burrow Kitchen Tea Bowl',
        'Garden Gnome in Reclaimed Oak',
        'Gryffindor Common Room, Late Morning',
    ]);
});

it('releases and pays out the delivered order', function (): void {
    expect(LedgerEntry::where('type', LedgerEntryType::Held)->count())->toBe(3)
        ->and(LedgerEntry::where('type', LedgerEntryType::Released)->count())->toBe(1)
        ->and(LedgerEntry::where('type', LedgerEntryType::PaidOut)->count())->toBe(1);

    expect(Payout::count())->toBe(1);

    $deliveredFulfillment = Fulfillment::where('status', FulfillmentStatus::Delivered)->firstOrFail();
    $payout = Payout::firstOrFail();
    expect($payout->seller_id)->toBe($deliveredFulfillment->seller_id)
        ->and($payout->amount_cents)->toBe($deliveredFulfillment->net_cents);
});

it('notifies sellers and the customer', function (): void {
    // 5 from order history, 11 from the seeded messaging threads — one
    // notification per posted message.
    expect(DatabaseNotification::count())->toBe(16);
});

it('seeds the two platform admins who can sign in at /admin/login', function (): void {
    expect(Admin::count())->toBe(2);

    foreach (AdminSeeder::ADMINS as $admin) {
        expect(Admin::where('email', $admin['email'])->count())->toBe(1);
    }
});

it('seeds one conversation of every messaging kind and one published FAQ', function (): void {
    expect(Conversation::count())->toBe(4)
        ->and(Message::count())->toBe(11)
        ->and(ListingFaq::count())->toBe(1);
});

it('keeps the database on a second run, only confirming the admins', function (): void {
    $log = CapturedStory::capture();
    $this->seed();

    expect(Seller::count())->toBe(7)
        ->and(Listing::count())->toBe(46)
        ->and(Order::count())->toBe(3)
        ->and(Admin::count())->toBe(2)
        ->and($log->line('seed.run', 'did')['data'])->toHaveKey('skipped', true);
});

it('tells the story of the seed run', function () use ($seedRun): void {
    $log = $seedRun->log ?? throw new RuntimeException('The seed run wrote no log.');

    expect($log->outline())->toContain('seed.run will', 'seed.run did')
        ->and($log->line('seed.run', 'did')['data'])->toHaveKey('seeder_count', 10);
});
