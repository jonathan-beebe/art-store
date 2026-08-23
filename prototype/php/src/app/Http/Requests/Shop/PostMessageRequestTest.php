<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Models\Conversation;

it('refuses a reply longer than the message limit', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    $conversation = Conversation::factory()->listingQuestion()->create(['customer_id' => $visitor->id]);

    $response = $this->post("/messages/{$conversation->id}", ['body' => str_repeat('a', 2001)]);

    $response->assertSessionHasErrors('body');
});

it('refuses an empty reply', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    $conversation = Conversation::factory()->listingQuestion()->create(['customer_id' => $visitor->id]);

    $response = $this->post("/messages/{$conversation->id}", ['body' => '']);

    $response->assertSessionHasErrors('body');
});

it('answers another visitors thread before it validates the form', function (): void {
    $this->visitor();
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = $this->post("/messages/{$conversation->id}", []);

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
});

it('reads the body the visitor typed', function (): void {
    $request = PostMessageRequest::create('/messages/1', 'POST', ['body' => 'Thanks for the quick answer.']);

    expect($request->body()->value)->toBe('Thanks for the quick answer.');
});
