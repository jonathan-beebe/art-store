<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Models\FulfillmentEvent;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;
use App\Models\Seller;

/**
 * A seller's default flow of two steps: print a label, then pack with no
 * further action.
 *
 * @return array{0: FulfillmentFlowStep, 1: FulfillmentFlowStep}
 */
$twoStepFlow = function (Seller $seller): array {
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $labelStep = FulfillmentFlowStep::factory()->printsLabel()->of($flow, 0)->create();
    $packStep = FulfillmentFlowStep::factory()->of($flow, 1)->create();

    return [$labelStep, $packStep];
};

it('appends a step_completed event and redirects to the label page for a step that prints a label', function () use ($twoStepFlow): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$labelStep] = $twoStepFlow($seller);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/orders/{$fulfillment->id}/steps/{$labelStep->id}", ['carrier' => 'Owl Post', 'tracking_number' => 'OP 1234']);

    $response->assertRedirect(route('seller.orders.label', $fulfillment->id));
    $event = FulfillmentEvent::where('fulfillment_id', $fulfillment->id)->sole();
    expect($event->kind)->toBe(FulfillmentEventKind::StepCompleted)
        ->and($event->fulfillment_flow_step_id)->toBe($labelStep->id)
        ->and($event->carrier)->toBe('Owl Post')
        ->and($event->tracking_number)->toBe('OP 1234');
});

it('redirects to the order with a flash status naming the step for a step that prints no label', function () use ($twoStepFlow): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$labelStep, $packStep] = $twoStepFlow($seller);
    $this->actingAs($seller, 'seller')->post("/seller/orders/{$fulfillment->id}/steps/{$labelStep->id}", ['carrier' => 'Owl Post', 'tracking_number' => 'OP 1234']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/orders/{$fulfillment->id}/steps/{$packStep->id}", []);

    $response->assertRedirect(route('seller.orders.show', $fulfillment->id));
    $response->assertSessionHas('status', $packStep->label.' — recorded.');
});

it('re-renders the domain refusal on a second submit of the same step, leaving one event', function () use ($twoStepFlow): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$labelStep] = $twoStepFlow($seller);
    $order = route('seller.orders.show', $fulfillment->id);
    $this->actingAs($seller, 'seller')->post("/seller/orders/{$fulfillment->id}/steps/{$labelStep->id}", ['carrier' => 'Owl Post', 'tracking_number' => 'OP 1234']);

    $response = $this->actingAs($seller, 'seller')
        ->from($order)
        ->followingRedirects()
        ->post("/seller/orders/{$fulfillment->id}/steps/{$labelStep->id}", ['carrier' => 'Owl Post', 'tracking_number' => 'OP 9999']);

    $response->assertOk();
    $response->assertSee("The step \"{$labelStep->label}\" is not the next step on this fulfillment.");
    expect(FulfillmentEvent::where('fulfillment_id', $fulfillment->id)->count())->toBe(1);
});

it('refuses a step submitted out of order', function () use ($twoStepFlow): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [, $packStep] = $twoStepFlow($seller);
    $order = route('seller.orders.show', $fulfillment->id);

    $response = $this->actingAs($seller, 'seller')
        ->from($order)
        ->followingRedirects()
        ->post("/seller/orders/{$fulfillment->id}/steps/{$packStep->id}", []);

    $response->assertOk();
    $response->assertSee("The step \"{$packStep->label}\" is not the next step on this fulfillment.");
    expect(FulfillmentEvent::where('fulfillment_id', $fulfillment->id)->count())->toBe(0);
});

it('refuses a step on a fulfillment that already shipped', function () use ($twoStepFlow): void {
    $seller = $this->seller();
    $fulfillment = $this->shippedFulfillmentFor($seller);
    [$labelStep] = $twoStepFlow($seller);
    $order = route('seller.orders.show', $fulfillment->id);

    $response = $this->actingAs($seller, 'seller')
        ->from($order)
        ->followingRedirects()
        ->post("/seller/orders/{$fulfillment->id}/steps/{$labelStep->id}", ['carrier' => 'Owl Post', 'tracking_number' => 'OP 1234']);

    $response->assertOk();
    $response->assertSee('A step cannot be completed on a fulfillment that is shipped.');
});

it('answers not found for another sellers fulfillment', function () use ($twoStepFlow): void {
    $other = $this->seller('Lovegood Curiosities');
    $fulfillment = $this->paidFulfillmentFor($other);
    [$labelStep] = $twoStepFlow($other);

    $response = $this->actingAs($this->seller(), 'seller')
        ->post("/seller/orders/{$fulfillment->id}/steps/{$labelStep->id}", ['carrier' => 'Owl Post', 'tracking_number' => 'OP 1234']);

    $response->assertNotFound();
});

it('answers not found for another sellers step on the sellers own fulfillment', function () use ($twoStepFlow): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$otherLabelStep] = $twoStepFlow($this->seller('Lovegood Curiosities'));

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/orders/{$fulfillment->id}/steps/{$otherLabelStep->id}", ['carrier' => 'Owl Post', 'tracking_number' => 'OP 1234']);

    $response->assertNotFound();
});

it('sends a signed-out visitor to seller sign-in', function () use ($twoStepFlow): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$labelStep] = $twoStepFlow($seller);

    $this->post("/seller/orders/{$fulfillment->id}/steps/{$labelStep->id}", [])
        ->assertRedirect(route('auth.seller.login'));
});
