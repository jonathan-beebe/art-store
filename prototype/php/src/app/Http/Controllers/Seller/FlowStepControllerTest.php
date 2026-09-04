<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Models\FulfillmentEvent;

it('appends a step_completed event and redirects to the label page for a step that prints a label', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$labelStep] = $this->flowFor($seller);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/orders/{$fulfillment->id}/steps/{$labelStep->id}", ['carrier' => 'Owl Post', 'tracking_number' => 'OP 1234']);

    $response->assertRedirect(route('seller.orders.label', $fulfillment->id));
    $event = FulfillmentEvent::where('fulfillment_id', $fulfillment->id)->sole();
    expect($event->kind)->toBe(FulfillmentEventKind::StepCompleted)
        ->and($event->fulfillment_flow_step_id)->toBe($labelStep->id)
        ->and($event->carrier)->toBe('Owl Post')
        ->and($event->tracking_number)->toBe('OP 1234');
});

it('redirects to the order with a flash status naming the step for a step that prints no label', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$labelStep, $packStep] = $this->flowFor($seller);
    $this->actingAs($seller, 'seller')->post("/seller/orders/{$fulfillment->id}/steps/{$labelStep->id}", ['carrier' => 'Owl Post', 'tracking_number' => 'OP 1234']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/orders/{$fulfillment->id}/steps/{$packStep->id}", []);

    $response->assertRedirect(route('seller.orders.show', $fulfillment->id));
    $response->assertSessionHas('status', $packStep->label.' — recorded.');
});

it('re-renders the domain refusal on a second submit of the same step, leaving one event', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$labelStep] = $this->flowFor($seller);
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

it('refuses a step submitted out of order', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [, $packStep] = $this->flowFor($seller);
    $order = route('seller.orders.show', $fulfillment->id);

    $response = $this->actingAs($seller, 'seller')
        ->from($order)
        ->followingRedirects()
        ->post("/seller/orders/{$fulfillment->id}/steps/{$packStep->id}", []);

    $response->assertOk();
    $response->assertSee("The step \"{$packStep->label}\" is not the next step on this fulfillment.");
    expect(FulfillmentEvent::where('fulfillment_id', $fulfillment->id)->count())->toBe(0);
});

it('refuses a step on a fulfillment that already shipped', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->shippedFulfillmentFor($seller);
    [$labelStep] = $this->flowFor($seller);
    $order = route('seller.orders.show', $fulfillment->id);

    $response = $this->actingAs($seller, 'seller')
        ->from($order)
        ->followingRedirects()
        ->post("/seller/orders/{$fulfillment->id}/steps/{$labelStep->id}", ['carrier' => 'Owl Post', 'tracking_number' => 'OP 1234']);

    $response->assertOk();
    $response->assertSee('A step cannot be completed on a fulfillment that is shipped.');
});

it('answers not found for another sellers fulfillment', function (): void {
    $other = $this->seller('Lovegood Curiosities');
    $fulfillment = $this->paidFulfillmentFor($other);
    [$labelStep] = $this->flowFor($other);

    $response = $this->actingAs($this->seller(), 'seller')
        ->post("/seller/orders/{$fulfillment->id}/steps/{$labelStep->id}", ['carrier' => 'Owl Post', 'tracking_number' => 'OP 1234']);

    $response->assertNotFound();
});

it('answers not found for another sellers step on the sellers own fulfillment', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$otherLabelStep] = $this->flowFor($this->seller('Lovegood Curiosities'));

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/orders/{$fulfillment->id}/steps/{$otherLabelStep->id}", ['carrier' => 'Owl Post', 'tracking_number' => 'OP 1234']);

    $response->assertNotFound();
});
