<?php

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Listings\ListingStatus;
use App\Models\Fulfillment;
use App\Models\Notification;
use Tests\CommerceTestCase;

final class DashboardControllerTest extends CommerceTestCase
{
    public function test_it_sends_a_signed_out_visitor_to_the_sign_in_page(): void
    {
        $this->get('/seller')->assertRedirect(route('auth.seller.login'));
    }

    public function test_it_renders_the_seller_dashboard(): void
    {
        $response = $this->actingAs($this->seller(), 'seller')->get('/seller');

        $response->assertOk();
        $response->assertSee('Dashboard');
    }

    public function test_it_links_the_built_stylesheet(): void
    {
        $response = $this->actingAs($this->seller(), 'seller')->get('/seller');

        $response->assertSee('/build/assets/', escape: false);
    }

    public function test_it_shows_a_flashed_magic_link_in_the_debug_alert(): void
    {
        $link = 'http://localhost:8000/auth/magic/abc123';

        $response = $this->actingAs($this->seller(), 'seller')
            ->withSession(['debug_magic_link' => $link])
            ->get('/seller');

        $response->assertSee($link, escape: false);
    }

    public function test_it_counts_the_sellers_listings_by_status(): void
    {
        $seller = $this->seller();
        $this->listing($seller, ['status' => ListingStatus::Draft]);
        $this->listing($seller, ['status' => ListingStatus::ForSale]);
        $this->listing($seller, ['status' => ListingStatus::ForSale]);

        $response = $this->actingAs($seller, 'seller')->get('/seller');

        $response->assertViewHas('tally', function (array $tally): bool {
            return $tally[0]->count === 1 && $tally[1]->count === 2 && $tally[2]->count === 0;
        });
    }

    public function test_it_leaves_another_sellers_listings_out_of_the_counts(): void
    {
        $seller = $this->seller();
        $this->listing($this->seller('Other Studio'), ['status' => ListingStatus::ForSale]);

        $response = $this->actingAs($seller, 'seller')->get('/seller');

        $response->assertViewHas('tally', fn (array $tally): bool => $tally[1]->count === 0);
    }

    public function test_it_counts_the_fulfillments_awaiting_shipment(): void
    {
        $seller = $this->seller();
        $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
        app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

        $response = $this->actingAs($seller, 'seller')->get('/seller');

        $response->assertViewHas('openFulfillments', 1);
    }

    public function test_it_holds_the_net_of_a_paid_order_in_escrow(): void
    {
        $seller = $this->seller();
        $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 10000]));
        app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

        $response = $this->actingAs($seller, 'seller')->get('/seller');

        $response->assertSee('$90.00');
    }

    public function test_it_makes_a_delivered_order_available(): void
    {
        $seller = $this->seller();
        $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 10000]));
        app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
        $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();
        app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM1', $this->moment('2026-08-21 10:00:00'));
        app(ConfirmDelivered::class)($fulfillment->fresh(), $this->moment('2026-08-22 10:00:00'));

        $response = $this->actingAs($seller, 'seller')->get('/seller');

        $response->assertViewHas('balance', fn ($balance): bool => $balance->available->cents === 9000 && $balance->held->cents === 0);
    }

    public function test_it_counts_unread_notifications(): void
    {
        $seller = $this->seller();
        Notification::create(['seller_id' => $seller->id, 'subject' => 'Item sold', 'body' => 'A print sold.']);
        Notification::create(['seller_id' => $seller->id, 'subject' => 'Read one', 'body' => 'Seen.', 'read_at' => now()]);

        $response = $this->actingAs($seller, 'seller')->get('/seller');

        $response->assertViewHas('unreadNotifications', 1);
    }

    public function test_it_shows_the_five_most_recent_notifications(): void
    {
        $seller = $this->seller();
        foreach (range(1, 6) as $number) {
            Notification::create(['seller_id' => $seller->id, 'subject' => "Notice {$number}", 'body' => 'Body.']);
        }

        $response = $this->actingAs($seller, 'seller')->get('/seller');

        $response->assertViewHas('notifications', fn ($notifications): bool => $notifications->count() === 5);
        $response->assertSee('Notice 6');
        $response->assertDontSee('Notice 1');
    }
}
