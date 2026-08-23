<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Models\Fulfillment;
use App\Models\Seller;
use Tests\CommerceTestCase;

final class OrderControllerTest extends CommerceTestCase
{
    public function test_it_sends_a_signed_out_visitor_to_the_sign_in_page(): void
    {
        $this->get('/seller/orders')->assertRedirect(route('auth.seller.login'));
    }

    public function test_it_groups_the_fulfillments_by_status(): void
    {
        $seller = $this->seller();
        $this->paidFulfillment($seller);

        $response = $this->actingAs($seller, 'seller')->get('/seller/orders');

        $response->assertOk();
        $response->assertSee('Awaiting shipment (1)');
        $response->assertSee('Shipped (0)');
        $response->assertSee('Delivered (0)');
    }

    public function test_it_keeps_another_sellers_fulfillments_off_the_page(): void
    {
        $this->paidFulfillment($this->seller('Other Studio'), 'Not Mine');

        $response = $this->actingAs($this->seller(), 'seller')->get('/seller/orders');

        $response->assertDontSee('Not Mine');
    }

    public function test_it_shows_the_shipping_address_and_the_sellers_items(): void
    {
        $seller = $this->seller();
        $fulfillment = $this->paidFulfillment($seller, 'Harbour at Dusk');

        $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

        $response->assertOk();
        $response->assertSee('Ada Lovelace');
        $response->assertSee('12 Analytical Way');
        $response->assertSee('Harbour at Dusk');
    }

    public function test_it_leaves_another_sellers_items_off_the_order(): void
    {
        $seller = $this->seller();
        $other = $this->seller('Other Studio');
        $customer = $this->verifiedCustomer();
        $order = $this->orderFor(
            $customer,
            $this->listing($seller, ['title' => 'Mine']),
            $this->listing($other, ['title' => 'Theirs']),
        );
        app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
        $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();

        $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

        $response->assertSee('Mine');
        $response->assertDontSee('Theirs');
    }

    public function test_it_offers_the_mark_shipped_form_while_a_fulfillment_awaits_shipment(): void
    {
        $seller = $this->seller();
        $fulfillment = $this->paidFulfillment($seller);

        $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

        $response->assertSee('for="carrier"', escape: false);
        $response->assertSee('for="tracking_number"', escape: false);
    }

    public function test_it_shows_the_shipment_details_once_shipped(): void
    {
        $seller = $this->seller();
        $fulfillment = $this->paidFulfillment($seller);
        app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM123', $this->moment('2026-08-21 10:00:00'));

        $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

        $response->assertSee('Royal Mail');
        $response->assertSee('RM123');
        $response->assertSee('Aug 21, 2026');
        $response->assertDontSee('for="carrier"', escape: false);
    }

    public function test_it_shows_the_delivered_timestamp(): void
    {
        $seller = $this->seller();
        $fulfillment = $this->paidFulfillment($seller);
        app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM123', $this->moment('2026-08-21 10:00:00'));
        app(ConfirmDelivered::class)($fulfillment->fresh(), $this->moment('2026-08-23 09:00:00'));

        $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

        $response->assertSee('Aug 23, 2026');
    }

    public function test_it_hides_another_sellers_fulfillment(): void
    {
        $fulfillment = $this->paidFulfillment($this->seller('Other Studio'));

        $response = $this->actingAs($this->seller(), 'seller')->get("/seller/orders/{$fulfillment->id}");

        $response->assertNotFound();
    }

    private function paidFulfillment(Seller $seller, string $title = 'Harbour at Dusk'): Fulfillment
    {
        $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['title' => $title]));
        app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

        return Fulfillment::where('seller_id', $seller->id)->sole();
    }
}
