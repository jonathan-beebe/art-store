<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('refuses a title or a message that is empty or longer than its limit', function (string $field, string $value): void {
    $seller = $this->seller();

    $response = $this->actingAs($this->admin(), 'admin')->post("/admin/sellers/{$seller->id}/messages", [
        'title' => 'Payout timing',
        'body' => 'Please review your listing photos.',
        $field => $value,
    ]);

    $response->assertSessionHasErrors($field);
})->with([
    'empty title' => ['title', ''],
    'title longer than the limit' => ['title', str_repeat('a', 121)],
    'empty body' => ['body', ''],
    'body longer than the limit' => ['body', str_repeat('a', 2001)],
]);

it('refuses a fulfillment id that belongs to a different seller', function (): void {
    $seller = $this->seller('Blue Kiln Studio');
    $other = $this->paidFulfillmentFor($this->seller('Rye Press'));

    $response = $this->actingAs($this->admin(), 'admin')->post("/admin/sellers/{$seller->id}/messages", [
        'title' => 'Payout timing',
        'body' => 'Please review your listing photos.',
        'fulfillment' => $other->id,
    ]);

    $response->assertSessionHasErrors('fulfillment');
});

it('accepts one of this sellers own fulfillments as context', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller('Blue Kiln Studio'));

    $response = $this->actingAs($this->admin(), 'admin')->post("/admin/sellers/{$fulfillment->seller_id}/messages", [
        'title' => 'Payout timing',
        'body' => 'Please review your listing photos.',
        'fulfillment' => $fulfillment->id,
    ]);

    $response->assertRedirect();
});

it('reads an emptied order select as no context', function (): void {
    $request = OpenSellerThreadRequest::create('/admin/sellers/1/messages', 'POST', ['title' => 'Support', 'body' => 'Hello.', 'fulfillment' => '']);

    expect($request->fulfillmentId())->toBeNull();
});

it('reads the title and body the admin typed', function (): void {
    $request = OpenSellerThreadRequest::create('/admin/sellers/1/messages', 'POST', ['title' => 'Payout timing', 'body' => 'Please review your listing photos.']);

    expect($request->title()->value)->toBe('Payout timing')
        ->and($request->body()->value)->toBe('Please review your listing photos.');
});
