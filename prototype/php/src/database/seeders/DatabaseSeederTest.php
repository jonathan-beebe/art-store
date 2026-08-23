<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Seller;
use Illuminate\Notifications\DatabaseNotification;

beforeEach(function (): void {
    $this->seed();
});

it('seeds four verified sellers', function (): void {
    expect(Seller::count())->toBe(4)
        ->and(Seller::whereNotNull('email_verified_at')->count())->toBe(4);
});

it('seeds listings across statuses and media', function (): void {
    expect(Listing::where('status', ListingStatus::ForSale)->count())->toBe(24)
        ->and(Listing::where('status', ListingStatus::Draft)->count())->toBe(3)
        ->and(Listing::where('status', ListingStatus::Sold)->count())->toBe(2);

    expect(Listing::where('status', ListingStatus::ForSale)->pluck('medium')->unique()->sort()->values()->all())
        ->toBe(['ceramic', 'painting', 'photography', 'print', 'sculpture', 'textile']);
});

it('seeds one verified customer with favorites', function (): void {
    $customer = Customer::where('email', 'casey@example.com')->first();

    expect($customer)->not->toBeNull();
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
    expect(DatabaseNotification::count())->toBe(5);
});
