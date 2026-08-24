<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

it('opens an event stream for the storefront visitor, signed in or not', function (): void {
    $response = $this->get('/events');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
});
