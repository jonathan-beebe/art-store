<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('refuses a message longer than the message limit', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/sellers/{$seller->id}/messages", ['body' => str_repeat('a', 2001)]);

    $response->assertSessionHasErrors('body');
});

it('refuses an empty message', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/sellers/{$seller->id}/messages", ['body' => '']);

    $response->assertSessionHasErrors('body');
});

it('reads the body the admin typed', function (): void {
    $request = SendMessageRequest::create('/admin/sellers/1/messages', 'POST', ['body' => 'Please review your listing photos.']);

    expect($request->body()->value)->toBe('Please review your listing photos.');
});
