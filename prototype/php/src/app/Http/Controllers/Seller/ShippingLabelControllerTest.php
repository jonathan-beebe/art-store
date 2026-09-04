<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Models\FulfillmentEvent;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;

it('renders the buyers address, the order id, and the label steps carrier and tracking number', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $labelStep = FulfillmentFlowStep::factory()->printsLabel()->of($flow, 0)->create();
    FulfillmentEvent::factory()->on($fulfillment)->completing($labelStep)
        ->create(['carrier' => 'Owl Post', 'tracking_number' => 'OP 4471']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}/label");

    $response->assertOk();
    $response->assertSee('Ada Lovelace');
    $response->assertSee('12 Analytical Way');
    $response->assertSee($fulfillment->order_id);
    $response->assertSee('Owl Post');
    $response->assertSee('OP 4471');
});

it('renders the address with a dash for carrier and tracking when no label step has completed', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}/label");

    $response->assertOk();
    $response->assertSee('Ada Lovelace');
    $response->assertSee('—', escape: false);
});

it('hides another sellers fulfillment', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller('Lovegood Curiosities'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/orders/{$fulfillment->id}/label");

    $response->assertNotFound();
});

it('sends a signed-out visitor to seller sign-in', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());

    $this->get("/seller/orders/{$fulfillment->id}/label")->assertRedirect(route('auth.seller.login'));
});
