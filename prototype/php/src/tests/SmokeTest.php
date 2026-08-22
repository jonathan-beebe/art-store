<?php

namespace Tests;

use App\Domain\Escrow\Fee;
use App\Domain\Listings\ListingStatus;
use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * One walk of the whole product over HTTP, from an empty database to a seller's
 * payout. Every step drives a real route and asserts both the database row it
 * wrote and the page the person would be looking at.
 *
 * Two people share one PHPUnit session: the seller holds the `seller` guard and
 * the customer is pinned by the identity cookie, which is what the storefront
 * uses to tell visitors apart.
 */
final class SmokeTest extends StorefrontTestCase
{
    private const SELLER_EMAIL = 'smoke-seller@example.com';

    private const CUSTOMER_EMAIL = 'smoke-buyer@example.com';

    private const LISTING_TITLE = 'Meadow at Low Tide';

    private const PRICE_DOLLARS = '480.00';

    // The payout period is derived from this date, so the weekday it lands on
    // does not change the walk.
    private const FROZEN_NOW = '2026-08-19 09:00:00';

    public function test_a_listing_travels_from_seller_sign_in_to_weekly_payout(): void
    {
        $this->travelTo(new DateTimeImmutable(self::FROZEN_NOW));
        Storage::fake('public');

        $seller = $this->signInSeller();
        $listing = $this->createListing();
        $this->markForSale($listing);

        $visitor = $this->arriveAsAnonymousVisitor();
        $this->viewListing($listing);
        $this->favoriteListing($listing);
        $this->addListingToCart($listing);

        $order = $this->placeGuestOrder();
        $this->verifyEmailFromDebugAlert($order);
        $this->payWithApprovedCard($order);

        $fulfillment = $this->seeTheSaleAsSeller($order);
        $this->markShipped($fulfillment);
        $this->confirmDelivery($order, $fulfillment);

        $this->runWeeklyPayout();
        $this->readEarningsAsSeller($seller);

        $this->assertSame($visitor->id, $order->refresh()->customer_id);
    }

    private function signInSeller(): Seller
    {
        $this->post('/seller/login', ['email' => self::SELLER_EMAIL])
            ->assertRedirect(route('auth.seller.login'));

        $page = $this->get(route('auth.seller.login'));
        $page->assertOk()->assertSee('Debug magic link:');

        $this->get($this->magicLinkRenderedIn($page->getContent()))->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticated('seller');

        $seller = Seller::sole();
        $this->assertSame(self::SELLER_EMAIL, $seller->email);
        $this->assertNotNull($seller->email_verified_at);

        return $seller;
    }

    private function createListing(): Listing
    {
        $this->post('/seller/listings', [
            'title' => self::LISTING_TITLE,
            'description' => 'Oil on linen.',
            'medium' => 'Painting',
            'dimensions' => '40 x 60 cm',
            'price' => self::PRICE_DOLLARS,
            'quantity' => 1,
            'image' => UploadedFile::fake()->image('meadow.jpg'),
        ])->assertRedirect();

        $listing = Listing::sole();
        $this->assertSame(ListingStatus::Draft, $listing->status);
        $this->assertSame($this->price()->cents, $listing->price_cents);
        Storage::disk('public')->assertExists($listing->image_path);

        return $listing;
    }

    private function markForSale(Listing $listing): void
    {
        $this->post("/seller/listings/{$listing->id}/status", ['status' => ListingStatus::ForSale->value])
            ->assertRedirect(route('seller.listings.index'));

        $this->assertSame(ListingStatus::ForSale, $listing->refresh()->status);
    }

    /**
     * The storefront hands a first-time visitor an anonymous customer row and
     * an identity cookie; the test client keeps the cookie by hand.
     */
    private function arriveAsAnonymousVisitor(): Customer
    {
        $this->get('/')->assertOk()->assertSee(self::LISTING_TITLE);

        $visitor = Customer::sole();
        $this->assertNull($visitor->email);

        return $this->arriveAs($visitor);
    }

    private function viewListing(Listing $listing): void
    {
        $this->get("/art/{$listing->slug}")
            ->assertOk()
            ->assertSee(self::LISTING_TITLE)
            ->assertSee($this->price()->format());

        $this->assertDatabaseHas('listing_events', ['listing_id' => $listing->id, 'type' => 'view']);
    }

    private function favoriteListing(Listing $listing): void
    {
        $this->post("/art/{$listing->slug}/favorite")->assertRedirect();

        $this->assertDatabaseHas('favorites', ['listing_id' => $listing->id]);
        $this->get('/favorites')->assertOk()->assertSee(self::LISTING_TITLE);
    }

    private function addListingToCart(Listing $listing): void
    {
        $this->post("/cart/{$listing->slug}")->assertRedirect(route('shop.cart'));

        $this->assertDatabaseHas('cart_items', ['listing_id' => $listing->id, 'quantity' => 1]);
        $this->get('/cart')->assertOk()->assertSee(self::LISTING_TITLE);
    }

    /**
     * A guest is never asked for a card: the order is placed unpaid and waits
     * for the address behind it to be verified.
     */
    private function placeGuestOrder(): Order
    {
        $this->get('/checkout')->assertOk()->assertDontSee('Card number');

        $placed = $this->post('/checkout', [
            'email' => self::CUSTOMER_EMAIL,
            'shipping_name' => 'Casey Whitfield',
            'shipping_line1' => '18 Harbour Road',
            'shipping_city' => 'Bristol',
            'shipping_region' => 'Bristol',
            'shipping_postal_code' => 'BS1 5TY',
            'shipping_country' => 'GB',
        ]);

        $order = Order::sole();
        $placed->assertRedirect(route('shop.order', $order));
        $this->assertSame(OrderStatus::PendingVerification, $order->status);
        $this->assertSame($this->price()->cents, $order->total_cents);

        return $order;
    }

    private function verifyEmailFromDebugAlert(Order $order): void
    {
        $page = $this->get(route('shop.order', $order));
        $page->assertOk()->assertSee('Check your email');

        $this->get($this->magicLinkRenderedIn($page->getContent()))
            ->assertRedirect(route('shop.order.pay', $order, absolute: false));

        $this->assertAuthenticated('customer');
        $this->assertNotNull(Customer::sole()->email_verified_at);
    }

    private function payWithApprovedCard(Order $order): void
    {
        $this->get(route('shop.order.pay', $order))->assertOk()->assertSee('Card number');

        $this->post(route('shop.order.pay.submit', $order), ['card_number' => '4242 4242 4242 4242'])
            ->assertRedirect(route('shop.order', $order));

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'approved', 'card_last_four' => '4242']);
        $this->assertDatabaseHas('ledger_entries', ['type' => 'held', 'amount_cents' => $this->net()->cents]);
    }

    private function seeTheSaleAsSeller(Order $order): Fulfillment
    {
        $this->get('/seller/notifications')
            ->assertOk()
            ->assertSee('Item sold')
            ->assertSee("Order #{$order->id} is paid.");

        $fulfillment = Fulfillment::sole();
        $this->assertSame(FulfillmentStatus::AwaitingShipment, $fulfillment->status);

        $this->get('/seller/orders')->assertOk()->assertSee(self::LISTING_TITLE);

        return $fulfillment;
    }

    private function markShipped(Fulfillment $fulfillment): void
    {
        $this->post("/seller/orders/{$fulfillment->id}/shipment", [
            'carrier' => 'Royal Mail',
            'tracking_number' => 'RM123456789GB',
        ])->assertRedirect(route('seller.orders.show', $fulfillment->id));

        $this->assertSame(FulfillmentStatus::Shipped, $fulfillment->refresh()->status);
        $this->get(route('seller.orders.show', $fulfillment->id))->assertOk()->assertSee('RM123456789GB');
    }

    private function confirmDelivery(Order $order, Fulfillment $fulfillment): void
    {
        $this->get(route('shop.order', $order))->assertOk()->assertSee('Confirm delivery');

        $this->post(route('shop.order.delivered', [$order, $fulfillment]))
            ->assertRedirect(route('shop.order', $order));

        $this->assertSame(FulfillmentStatus::Delivered, $fulfillment->refresh()->status);
        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
        $this->assertDatabaseHas('ledger_entries', ['type' => 'released', 'amount_cents' => $this->net()->cents]);
    }

    private function runWeeklyPayout(): void
    {
        $nextMonday = (new DateTimeImmutable(self::FROZEN_NOW))->modify('next monday')->format('Y-m-d');

        $this->assertSame(0, Artisan::call('payouts:run', ['--as-of' => $nextMonday]));
        $this->assertSame($this->net()->cents, Payout::sole()->amount_cents);
    }

    private function readEarningsAsSeller(Seller $seller): void
    {
        $page = $this->get('/seller/earnings');

        $page->assertOk()->assertSee($this->net()->format());
        $this->assertSame($this->net()->cents, $seller->fresh()->escrowBalance()->paidOut->cents);
    }

    private function price(): Money
    {
        return Money::fromDollars(self::PRICE_DOLLARS);
    }

    private function net(): Money
    {
        return Fee::net($this->price());
    }

    private function magicLinkRenderedIn(string $html): string
    {
        $found = preg_match('#href="([^"]*/auth/magic/[^"]+)"#', $html, $matches) === 1;
        $this->assertTrue($found, 'The debug alert rendered no magic link.');

        return $matches[1];
    }
}
