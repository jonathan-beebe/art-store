<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Conversation;

it('refuses a reply that is empty or longer than the message limit', function (string $body): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->adminSeller()->create();

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/messages/{$conversation->id}", ['body' => $body]);

    $response->assertSessionHasErrors('body');
})->with([
    'empty' => [''],
    'longer than the limit' => [str_repeat('a', 2001)],
]);

it('refuses the desk before it validates the form, on a thread neither support kind admits', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/messages/{$conversation->id}", []);

    $response->assertForbidden();
    $response->assertSessionHasNoErrors();
});

it('reads the body the admin typed', function (): void {
    $request = PostMessageRequest::create('/admin/messages/1', 'POST', ['body' => "I'll take a look."]);

    expect($request->body()->value)->toBe("I'll take a look.");
});
