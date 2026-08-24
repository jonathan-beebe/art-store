<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Conversation;

it('refuses a reply longer than the message limit', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/messages/{$conversation->id}", ['body' => str_repeat('a', 2001)]);

    $response->assertSessionHasErrors('body');
});

it('refuses an empty reply', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/messages/{$conversation->id}", ['body' => '']);

    $response->assertSessionHasErrors('body');
});

it('answers another sellers thread before it validates the form', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = $this->actingAs($this->seller(), 'seller')
        ->post("/seller/messages/{$conversation->id}", []);

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
});

it('reads the body the seller typed', function (): void {
    $request = PostMessageRequest::create('/seller/messages/1', 'POST', ['body' => 'It ships within 3 days.']);

    expect($request->body()->value)->toBe('It ships within 3 days.');
});
