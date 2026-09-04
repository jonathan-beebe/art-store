<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

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

it('refuses a label step missing what it prints with', function (array $overrides, string $field) use ($twoStepFlow): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$labelStep] = $twoStepFlow($seller);
    $form = $overrides + ['carrier' => 'Owl Post', 'tracking_number' => 'OP 1234'];

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/orders/{$fulfillment->id}/steps/{$labelStep->id}", $form);

    $response->assertSessionHasErrors($field);
    expect(FulfillmentEvent::count())->toBe(0);
})->with([
    'no carrier' => [['carrier' => ''], 'carrier'],
    'no tracking number' => [['tracking_number' => ''], 'tracking_number'],
]);

it('refuses a carrier or tracking number submitted to a step that prints no label', function (array $form, string $field) use ($twoStepFlow): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [, $packStep] = $twoStepFlow($seller);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/orders/{$fulfillment->id}/steps/{$packStep->id}", $form);

    $response->assertSessionHasErrors($field);
    expect(FulfillmentEvent::count())->toBe(0);
})->with([
    'a carrier' => [['carrier' => 'Owl Post'], 'carrier'],
    'a tracking number' => [['tracking_number' => 'OP 1234'], 'tracking_number'],
]);

it('answers another sellers fulfillment before it validates the form', function () use ($twoStepFlow): void {
    $other = $this->seller('Lovegood Curiosities');
    $fulfillment = $this->paidFulfillmentFor($other);
    [$labelStep] = $twoStepFlow($other);

    $response = $this->actingAs($this->seller(), 'seller')
        ->post("/seller/orders/{$fulfillment->id}/steps/{$labelStep->id}", []);

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
    expect(FulfillmentEvent::count())->toBe(0);
});
