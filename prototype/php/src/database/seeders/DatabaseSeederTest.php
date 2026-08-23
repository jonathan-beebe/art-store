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
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_four_verified_sellers(): void
    {
        $this->seed();

        $this->assertSame(4, Seller::count());
        $this->assertSame(4, Seller::whereNotNull('email_verified_at')->count());
    }

    public function test_it_seeds_listings_across_statuses_and_media(): void
    {
        $this->seed();

        $this->assertSame(24, Listing::where('status', ListingStatus::ForSale)->count());
        $this->assertSame(3, Listing::where('status', ListingStatus::Draft)->count());
        $this->assertSame(2, Listing::where('status', ListingStatus::Sold)->count());

        $media = Listing::where('status', ListingStatus::ForSale)->pluck('medium')->unique()->sort()->values()->all();
        $this->assertSame(['ceramic', 'painting', 'photography', 'print', 'sculpture', 'textile'], $media);
    }

    public function test_it_seeds_one_verified_customer_with_favorites(): void
    {
        $this->seed();

        $customer = Customer::where('email', 'casey@example.com')->first();

        $this->assertNotNull($customer);
        $this->assertNotNull($customer->email_verified_at);
        $this->assertSame(3, Favorite::where('customer_id', $customer->id)->count());
        $this->assertGreaterThanOrEqual(6, ListingEvent::count());
    }

    public function test_it_seeds_order_history_for_two_sellers(): void
    {
        $this->seed();

        $this->assertSame(3, Order::count());
        $this->assertSame(3, Order::where('status', '!=', OrderStatus::PendingVerification)->count());

        $this->assertSame(1, Fulfillment::where('status', FulfillmentStatus::AwaitingShipment)->count());
        $this->assertSame(1, Fulfillment::where('status', FulfillmentStatus::Shipped)->count());
        $this->assertSame(1, Fulfillment::where('status', FulfillmentStatus::Delivered)->count());

        $sellerIds = Fulfillment::query()->distinct()->pluck('seller_id');
        $this->assertCount(2, $sellerIds);

        $this->assertSame(3, Payment::count());
    }

    public function test_it_releases_and_pays_out_the_delivered_order(): void
    {
        $this->seed();

        $this->assertSame(3, LedgerEntry::where('type', LedgerEntryType::Held)->count());
        $this->assertSame(1, LedgerEntry::where('type', LedgerEntryType::Released)->count());
        $this->assertSame(1, LedgerEntry::where('type', LedgerEntryType::PaidOut)->count());

        $this->assertSame(1, Payout::count());

        $deliveredFulfillment = Fulfillment::where('status', FulfillmentStatus::Delivered)->firstOrFail();
        $payout = Payout::firstOrFail();
        $this->assertSame($deliveredFulfillment->seller_id, $payout->seller_id);
        $this->assertSame($deliveredFulfillment->net_cents, $payout->amount_cents);
    }

    public function test_it_notifies_sellers_and_the_customer(): void
    {
        $this->seed();

        $this->assertSame(5, Notification::count());
    }
}
