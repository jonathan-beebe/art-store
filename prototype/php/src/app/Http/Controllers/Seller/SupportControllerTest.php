<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Conversation;
use Illuminate\Support\Facades\Config;

it('opens a fresh, empty admin/seller thread and lands on it', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/support');

    $conversation = Conversation::sole();
    expect($conversation->kind->value)->toBe('admin_seller')
        ->and($conversation->seller_id)->toBe($seller->id)
        ->and($conversation->admin_id)->toBeNull()
        ->and($conversation->title)->toBe('Support');
    $response->assertRedirect(route('seller.messages.show', $conversation));
});

it('opens a fresh thread every visit, rather than finding one already open', function (): void {
    $seller = $this->seller();
    $this->actingAs($seller, 'seller')->get('/seller/support');

    $this->actingAs($seller, 'seller')->get('/seller/support');

    expect(Conversation::count())->toBe(2);
});

it('trips the conversation-open limit on a second visit within the window, rendering the sellers own 429 page', function (): void {
    Config::set('rate_limits.conversation_open', RateLimitValue::parse('1/1h', 'RATE_LIMIT_CONVERSATION_OPEN'));
    $seller = $this->seller();
    $this->actingAs($seller, 'seller')->get('/seller/support');

    $response = $this->actingAs($seller, 'seller')->get('/seller/support');

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
});
