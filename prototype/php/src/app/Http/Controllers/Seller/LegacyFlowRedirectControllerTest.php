<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

it('redirects the old flow address to the default workflows edit page', function (): void {
    $seller = $this->seller();
    [$labelStep] = $this->flowFor($seller, 'How I ship');
    $flow = $labelStep->fulfillmentFlow;
    $flow->update(['is_default' => true]);

    $response = $this->actingAs($seller, 'seller')->get('/seller/orders/flow');

    $response->assertRedirect(route('seller.workflows.edit', $flow));
    $response->assertStatus(301);
});

it('redirects the old flow address to the workflows list for a seller with none yet', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/orders/flow');

    $response->assertRedirect(route('seller.workflows.index'));
    $response->assertStatus(301);
});
