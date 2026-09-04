<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('refuses a title or a message past the domain limit', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->post('/seller/support/new', [
        'title' => str_repeat('a', 121),
        'body' => str_repeat('a', 2001),
    ]);

    $response->assertSessionHasErrors(['title', 'body']);
});

it('reads the title and the message the seller typed', function (): void {
    $request = OpenSupportThreadRequest::create('/seller/support', 'POST', [
        'title' => 'Payout timing for August',
        'body' => 'My payout has not appeared.',
    ]);

    expect($request->title()->value)->toBe('Payout timing for August')
        ->and($request->body()->value)->toBe('My payout has not appeared.');
});

it('reads no fulfillment when the form sends none', function (): void {
    $request = OpenSupportThreadRequest::create('/seller/support', 'POST', [
        'title' => 'Payout timing for August',
        'body' => 'My payout has not appeared.',
    ]);

    expect($request->fulfillmentId())->toBeNull();
});
