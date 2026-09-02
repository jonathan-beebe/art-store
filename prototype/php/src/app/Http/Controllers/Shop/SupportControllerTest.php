<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Conversation;
use Illuminate\Support\Facades\Config;

it('redirects a signed-out visitor to sign in rather than opening a thread', function (): void {
    $response = $this->get('/support');

    $response->assertRedirect(route('auth.customer.login'));
    expect(Conversation::count())->toBe(0);
});

it('opens a fresh, empty admin/customer thread and lands on it', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');

    $response = $this->get('/support');

    $conversation = Conversation::sole();
    expect($conversation->kind->value)->toBe('admin_customer')
        ->and($conversation->customer_id)->toBe($customer->id)
        ->and($conversation->admin_id)->toBeNull()
        ->and($conversation->title)->toBe('Support');
    $response->assertRedirect(route('shop.messages.show', $conversation));
});

it('opens a fresh thread every visit, rather than finding one already open', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    $this->get('/support');

    $this->get('/support');

    expect(Conversation::count())->toBe(2);
});

it('trips the conversation-open limit on a second visit within the window', function (): void {
    Config::set('rate_limits.conversation_open', RateLimitValue::parse('1/1h', 'RATE_LIMIT_CONVERSATION_OPEN'));
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    $this->get('/support');

    $response = $this->get('/support');

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
});
