<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

it('opens an event stream for the signed-in seller', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/events');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
});
