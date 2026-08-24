<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Escrow\Fee;
use App\Domain\Listings\ListingStatus;
use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

const SMOKE_SELLER_EMAIL = 'smoke-seller@example.com';

const SMOKE_CUSTOMER_EMAIL = 'smoke-buyer@example.com';

const SMOKE_LISTING_TITLE = 'Meadow at Low Tide';

const SMOKE_PRICE_DOLLARS = '480.00';

// The payout period is derived from this date, so the weekday it lands on
// does not change the walk.
const SMOKE_FROZEN_NOW = '2026-08-19 09:00:00';

$price = fn (): Money => Money::fromDollars(SMOKE_PRICE_DOLLARS);

$net = fn (): Money => Fee::net($price());

/**
 * One walk of the whole product over HTTP, from an empty database to a
 * seller's payout. Every step drives a real route and asserts both the
 * database row it wrote and the page the person would be looking at.
 *
 * Two people share this walk: the seller holds the `seller` guard and the
 * customer is pinned by the identity cookie, which is what the storefront
 * uses to tell visitors apart.
 */
it('carries a listing from seller sign-in to weekly payout', function () use ($price, $net): void {
    $magicLinkRenderedIn = function (string|false $html): string {
        if (! is_string($html) || preg_match('#href="([^"]*/auth/magic/[^"]+)"#', $html, $matches) !== 1) {
            $this->fail('The debug alert rendered no magic link.');
        }

        return $matches[1];
    };

    $signInSeller = function () use ($magicLinkRenderedIn): Seller {
        $this->post('/seller/login', ['email' => SMOKE_SELLER_EMAIL])
            ->assertRedirect(route('auth.seller.login'));

        $page = $this->get(route('auth.seller.login'));
        $page->assertOk()->assertSee('Debug magic link:');

        $this->get($magicLinkRenderedIn($page->getContent()))->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticated('seller');

        $seller = Seller::sole();
        expect($seller->email)->toBe(SMOKE_SELLER_EMAIL)
            ->and($seller->email_verified_at)->not->toBeNull();

        return $seller;
    };

    $createListing = function () use ($price): Listing {
        $this->post('/seller/listings', [
            'title' => SMOKE_LISTING_TITLE,
            'description' => 'Oil on linen.',
            'medium' => 'Painting',
            'dimensions' => '40 x 60 cm',
            'price' => SMOKE_PRICE_DOLLARS,
            'quantity' => 1,
            'image' => UploadedFile::fake()->image('meadow.jpg'),
        ])->assertRedirect();

        $listing = Listing::sole();
        expect($listing->status)->toBe(ListingStatus::Draft)
            ->and($listing->price_cents)->toBe($price()->cents);
        Storage::disk('public')->assertExists($listing->image_path ?? $this->fail('The listing saved no image.'));

        return $listing;
    };

    $markForSale = function (Listing $listing): void {
        $this->post("/seller/listings/{$listing->id}/status", ['status' => ListingStatus::ForSale->value])
            ->assertRedirect(route('seller.listings.index'));

        expect($listing->refresh()->status)->toBe(ListingStatus::ForSale);
    };

    /**
     * The storefront hands a first-time visitor an anonymous customer row and
     * an identity cookie; the test client does not keep the one the middleware
     * queues, so the walk pins its visitor up front.
     */
    $arriveAsAnonymousVisitor = function (): Customer {
        $this->get('/')->assertOk()->assertSee(SMOKE_LISTING_TITLE);

        $visitor = Customer::sole();
        expect($visitor->email)->toBeNull();

        return $this->arriveAs($visitor);
    };

    $viewListing = function (Listing $listing) use ($price): void {
        $this->get("/art/{$listing->slug}")
            ->assertOk()
            ->assertSee(SMOKE_LISTING_TITLE)
            ->assertSee($price()->format());

        $this->assertDatabaseHas('listing_events', ['listing_id' => $listing->id, 'type' => 'view']);
    };

    /**
     * The visitor asks a question on the listing, unauthenticated. The
     * question route opens the thread and lands the visitor on it.
     */
    $askSellerAQuestion = function (Listing $listing): Conversation {
        $asked = $this->post("/art/{$listing->slug}/questions", ['body' => 'Does this arrive ready to hang?']);

        $conversation = Conversation::sole();
        $asked->assertRedirect(route('shop.messages.show', $conversation));
        expect(Message::sole()->body)->toBe('Does this arrive ready to hang?');

        $this->get(route('shop.messages.show', $conversation))
            ->assertOk()
            ->assertSee('Does this arrive ready to hang?');

        return $conversation;
    };

    /**
     * The seller reads the question, replies, and publishes the reply as the
     * listing's FAQ entry — the same "Publish as FAQ" form the thread page
     * offers, pre-filled from the question and the reply it names.
     */
    $sellerRepliesAndPublishesFaq = function (Conversation $conversation, Listing $listing): void {
        $this->get(route('seller.messages.show', $conversation))
            ->assertOk()
            ->assertSee('Does this arrive ready to hang?');

        $this->post(route('seller.messages.store', $conversation), [
            'body' => 'Yes, it ships ready to hang with the wire already attached.',
        ])->assertRedirect(route('seller.messages.show', $conversation));

        $answer = Message::where('body', 'Yes, it ships ready to hang with the wire already attached.')->sole();

        $this->post(route('seller.listings.faqs.store', $listing), [
            'question' => 'Does this arrive ready to hang?',
            'answer' => 'Yes, it ships ready to hang with the wire already attached.',
            'source_message_id' => $answer->id,
        ])->assertRedirect(route('seller.listings.faqs.index', $listing));

        $this->assertDatabaseHas('listing_faqs', ['listing_id' => $listing->id, 'source_message_id' => $answer->id]);
    };

    /**
     * The published answer reads on the listing page for a second visitor,
     * one who asked nothing and holds no thread — the storefront visibility
     * the feature exists for. The walk's own visitor is pinned again after,
     * since the identity cookie is what carries them through checkout.
     */
    $seeFaqAsAnotherVisitor = function (Listing $listing, Customer $asker): void {
        $this->arriveAs($this->anonymousCustomer());

        $this->get("/art/{$listing->slug}")
            ->assertOk()
            ->assertSee('Does this arrive ready to hang?')
            ->assertSee('Yes, it ships ready to hang with the wire already attached.');

        $this->arriveAs($asker);
    };

    $favoriteListing = function (Listing $listing): void {
        $this->post("/art/{$listing->slug}/favorite")->assertRedirect();

        $this->assertDatabaseHas('favorites', ['listing_id' => $listing->id]);
        $this->get('/favorites')->assertOk()->assertSee(SMOKE_LISTING_TITLE);
    };

    $addListingToCart = function (Listing $listing): void {
        $this->post("/cart/{$listing->slug}")->assertRedirect(route('shop.cart'));

        $this->assertDatabaseHas('cart_items', ['listing_id' => $listing->id, 'quantity' => 1]);
        $this->get('/cart')->assertOk()->assertSee(SMOKE_LISTING_TITLE);
    };

    /**
     * A guest is never asked for a card: the order is placed unpaid and waits
     * for the address behind it to be verified.
     */
    $placeGuestOrder = function () use ($price): Order {
        $this->get('/checkout')->assertOk()->assertDontSee('Card number');

        $placed = $this->post('/checkout', [
            'email' => SMOKE_CUSTOMER_EMAIL,
            'shipping_name' => 'Casey Whitfield',
            'shipping_line1' => '18 Harbour Road',
            'shipping_city' => 'Bristol',
            'shipping_region' => 'Bristol',
            'shipping_postal_code' => 'BS1 5TY',
            'shipping_country' => 'GB',
        ]);

        $order = Order::sole();
        $placed->assertRedirect(route('shop.order', $order));
        expect($order->status)->toBe(OrderStatus::PendingVerification)
            ->and($order->total_cents)->toBe($price()->cents);

        return $order;
    };

    $verifyEmailFromDebugAlert = function (Order $order) use ($magicLinkRenderedIn): void {
        $page = $this->get(route('shop.order', $order));
        $page->assertOk()->assertSee('Check your email');

        $this->get($magicLinkRenderedIn($page->getContent()))
            ->assertRedirect(route('shop.order.pay', $order, absolute: false));

        $this->assertAuthenticated('customer');
        expect(Customer::findOrFail($order->customer_id)->email_verified_at)->not->toBeNull();
    };

    $payWithApprovedCard = function (Order $order) use ($net): void {
        $this->get(route('shop.order.pay', $order))->assertOk()->assertSee('Card number');

        $this->post(route('shop.order.pay.submit', $order), ['card_number' => '4242 4242 4242 4242'])
            ->assertRedirect(route('shop.order', $order));

        expect($order->refresh()->status)->toBe(OrderStatus::Paid);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'approved', 'card_last_four' => '4242']);
        $this->assertDatabaseHas('ledger_entries', ['type' => 'held', 'amount_cents' => $net()->cents]);
    };

    $seeTheSaleAsSeller = function (Order $order): Fulfillment {
        $this->get('/seller/notifications')
            ->assertOk()
            ->assertSee('Item sold')
            ->assertSee("Order #{$order->id} is paid.");

        $fulfillment = Fulfillment::sole();
        expect($fulfillment->status)->toBe(FulfillmentStatus::AwaitingShipment);

        $this->get('/seller/orders')->assertOk()->assertSee(SMOKE_LISTING_TITLE);

        return $fulfillment;
    };

    $markShipped = function (Fulfillment $fulfillment): void {
        $this->post("/seller/orders/{$fulfillment->id}/shipment", [
            'carrier' => 'Royal Mail',
            'tracking_number' => 'RM123456789GB',
        ])->assertRedirect(route('seller.orders.show', $fulfillment->id));

        expect($fulfillment->refresh()->status)->toBe(FulfillmentStatus::Shipped);
        $this->get(route('seller.orders.show', $fulfillment->id))->assertOk()->assertSee('RM123456789GB');
    };

    $confirmDelivery = function (Order $order, Fulfillment $fulfillment) use ($net): void {
        $this->get(route('shop.order', $order))->assertOk()->assertSee('Confirm delivery');

        $this->post(route('shop.order.delivered', [$order, $fulfillment]))
            ->assertRedirect(route('shop.order', $order));

        expect($fulfillment->refresh()->status)->toBe(FulfillmentStatus::Delivered)
            ->and($order->refresh()->status)->toBe(OrderStatus::Delivered);
        $this->assertDatabaseHas('ledger_entries', ['type' => 'released', 'amount_cents' => $net()->cents]);
    };

    $runWeeklyPayout = function () use ($net): void {
        $nextMonday = (new DateTimeImmutable(SMOKE_FROZEN_NOW))->modify('next monday')->format('Y-m-d');

        expect(Artisan::call('payouts:run', ['--as-of' => $nextMonday]))->toBe(0);
        expect(Payout::sole()->amount_cents)->toBe($net()->cents);
    };

    $readEarningsAsSeller = function (Seller $seller) use ($net): void {
        $page = $this->get('/seller/earnings');

        $page->assertOk()->assertSee($net()->format());
        expect($seller->refresh()->escrowBalance()->paidOut->cents)->toBe($net()->cents);
    };

    $this->travelTo(new DateTimeImmutable(SMOKE_FROZEN_NOW));
    Storage::fake('public');

    $seller = $signInSeller();
    $listing = $createListing();
    $markForSale($listing);

    $visitor = $arriveAsAnonymousVisitor();
    $viewListing($listing);

    $conversation = $askSellerAQuestion($listing);
    $sellerRepliesAndPublishesFaq($conversation, $listing);
    $seeFaqAsAnotherVisitor($listing, $visitor);

    $favoriteListing($listing);
    $addListingToCart($listing);

    $order = $placeGuestOrder();
    $verifyEmailFromDebugAlert($order);
    $payWithApprovedCard($order);

    $fulfillment = $seeTheSaleAsSeller($order);
    $markShipped($fulfillment);
    $confirmDelivery($order, $fulfillment);

    $runWeeklyPayout();
    $readEarningsAsSeller($seller);

    expect($order->refresh()->customer_id)->toBe($visitor->id);
});
