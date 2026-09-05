<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Models\FulfillmentFlow;

it('makes a named workflow the default, taking the role from the old one', function (): void {
    $seller = $this->seller();
    [$labelStep] = $this->flowFor($seller, 'How I ship');
    $defaultFlow = $labelStep->fulfillmentFlow;
    $defaultFlow->update(['is_default' => true]);
    $other = FulfillmentFlow::factory()->create(['seller_id' => $seller->id, 'name' => 'Framed pieces']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/workflows/{$other->id}/default");

    $response->assertRedirect(route('seller.workflows.index'));
    expect($defaultFlow->refresh()->is_default)->toBeFalse()
        ->and($other->refresh()->is_default)->toBeTrue();
});

it('answers not found making another sellers workflow the default', function (): void {
    $other = FulfillmentFlow::factory()->create(['seller_id' => $this->seller('Other Studio')->id]);

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/workflows/{$other->id}/default");

    $response->assertNotFound();
});
