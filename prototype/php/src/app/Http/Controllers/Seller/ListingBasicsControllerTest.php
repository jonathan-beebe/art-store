<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

it('shows the buyer-view panel beside the basics form', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertSee('What buyers see');
    $response->assertSee('Harbour at Dusk');
});
