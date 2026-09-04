<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Models\FulfillmentFlow;

it('shows the buyer-view panel beside the basics form', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertSee('What buyers see');
    $response->assertSee('Harbour at Dusk');
});

it('hides the workflow picker for a seller with one workflow or none', function (int $flowCount): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    FulfillmentFlow::factory()->count($flowCount)->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertDontSee('name="fulfillment_flow_id"', false);
})->with([0, 1]);

it('shows the workflow picker, the default marked, for a seller with more than one', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id, 'name' => 'How I ship']);
    FulfillmentFlow::factory()->create(['seller_id' => $seller->id, 'name' => 'Framed pieces']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertSee('name="fulfillment_flow_id"', false);
    $response->assertSee('How I ship (default)');
    $response->assertSee('Framed pieces');
});
