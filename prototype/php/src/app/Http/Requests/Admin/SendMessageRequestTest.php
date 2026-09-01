<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('refuses a message that is empty or longer than the message limit', function (string $body): void {
    $seller = $this->seller();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/sellers/{$seller->id}/messages", ['body' => $body]);

    $response->assertSessionHasErrors('body');
})->with([
    'empty' => [''],
    'longer than the limit' => [str_repeat('a', 2001)],
]);

it('reads the body the admin typed', function (): void {
    $request = SendMessageRequest::create('/admin/sellers/1/messages', 'POST', ['body' => 'Please review your listing photos.']);

    expect($request->body()->value)->toBe('Please review your listing photos.');
});
