<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\Fulfillment;
use App\Models\Notification;
use App\Models\Seller;
use Tests\CommerceTestCase;

final class ShipmentControllerTest extends CommerceTestCase
{
    public function test_it_sends_a_signed_out_visitor_to_the_sign_in_page(): void
    {
        $fulfillment = $this->paidFulfillment($this->seller());

        $this->post("/seller/orders/{$fulfillment->id}/shipment", $this->form())
            ->assertRedirect(route('auth.seller.login'));
    }

    public function test_it_marks_the_fulfillment_shipped(): void
    {
        $seller = $this->seller();
        $fulfillment = $this->paidFulfillment($seller);

        $response = $this->actingAs($seller, 'seller')
            ->post("/seller/orders/{$fulfillment->id}/shipment", $this->form());

        $response->assertRedirect(route('seller.orders.show', $fulfillment->id));
        $shipped = $fulfillment->fresh();
        $this->assertSame(FulfillmentStatus::Shipped, $shipped->status);
        $this->assertSame('Royal Mail', $shipped->carrier);
        $this->assertSame('RM123', $shipped->tracking_number);
        $this->assertNotNull($shipped->shipped_at);
    }

    public function test_it_rolls_the_order_up_to_shipped(): void
    {
        $seller = $this->seller();
        $fulfillment = $this->paidFulfillment($seller);

        $this->actingAs($seller, 'seller')->post("/seller/orders/{$fulfillment->id}/shipment", $this->form());

        $this->assertSame(OrderStatus::Shipped, $fulfillment->order->fresh()->status);
    }

    public function test_it_notifies_the_customer(): void
    {
        $seller = $this->seller();
        $fulfillment = $this->paidFulfillment($seller);

        $this->actingAs($seller, 'seller')->post("/seller/orders/{$fulfillment->id}/shipment", $this->form());

        $this->assertSame(1, Notification::where('customer_id', $fulfillment->order->customer_id)->count());
    }

    public function test_it_rejects_a_shipment_without_a_carrier(): void
    {
        $seller = $this->seller();
        $fulfillment = $this->paidFulfillment($seller);

        $response = $this->actingAs($seller, 'seller')
            ->post("/seller/orders/{$fulfillment->id}/shipment", $this->form(['carrier' => '']));

        $response->assertSessionHasErrors('carrier');
        $this->assertSame(FulfillmentStatus::AwaitingShipment, $fulfillment->fresh()->status);
    }

    public function test_it_rejects_a_shipment_without_a_tracking_number(): void
    {
        $seller = $this->seller();
        $fulfillment = $this->paidFulfillment($seller);

        $response = $this->actingAs($seller, 'seller')
            ->post("/seller/orders/{$fulfillment->id}/shipment", $this->form(['tracking_number' => '']));

        $response->assertSessionHasErrors('tracking_number');
    }

    public function test_it_refuses_to_ship_a_fulfillment_that_already_shipped(): void
    {
        $seller = $this->seller();
        $fulfillment = $this->paidFulfillment($seller);
        $this->actingAs($seller, 'seller')->post("/seller/orders/{$fulfillment->id}/shipment", $this->form());

        $response = $this->actingAs($seller, 'seller')
            ->post("/seller/orders/{$fulfillment->id}/shipment", $this->form(['tracking_number' => 'RM999']));

        $response->assertStatus(422);
        $this->assertSame('RM123', $fulfillment->fresh()->tracking_number);
    }

    public function test_it_refuses_to_ship_another_sellers_fulfillment(): void
    {
        $fulfillment = $this->paidFulfillment($this->seller('Other Studio'));

        $response = $this->actingAs($this->seller(), 'seller')
            ->post("/seller/orders/{$fulfillment->id}/shipment", $this->form());

        $response->assertNotFound();
        $this->assertSame(FulfillmentStatus::AwaitingShipment, $fulfillment->fresh()->status);
    }

    private function paidFulfillment(Seller $seller): Fulfillment
    {
        $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
        app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

        return Fulfillment::where('seller_id', $seller->id)->sole();
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + ['carrier' => 'Royal Mail', 'tracking_number' => 'RM123'];
    }
}
