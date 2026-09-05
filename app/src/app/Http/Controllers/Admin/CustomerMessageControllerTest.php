<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Conversation;
use App\Models\CustomerBlock;
use App\Models\Message;
use Illuminate\Support\Facades\Config;

it('opens the customers admin thread, titled, and lands on it', function (): void {
    $admin = $this->admin();
    $customer = $this->verifiedCustomer();

    $response = $this->actingAs($admin, 'admin')->post("/admin/customers/{$customer->id}/messages", [
        'title' => 'Where is my order?',
        'body' => 'We looked into your order.',
    ]);

    $conversation = Conversation::sole();
    expect($conversation->kind->value)->toBe('admin_customer')
        ->and($conversation->title)->toBe('Where is my order?')
        ->and($conversation->customer_id)->toBe($customer->id)
        ->and($conversation->admin_id)->toBe($admin->id)
        ->and($conversation->order_id)->toBeNull()
        ->and($conversation->subject_key)->toBeNull();
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'We looked into your order.')->exists())->toBeTrue();
    $response->assertRedirect(route('admin.messages.show', $conversation));
});

it('carries one of the customers own orders as the threads order context', function (): void {
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($this->seller()));

    $this->actingAs($this->admin(), 'admin')->post("/admin/customers/{$customer->id}/messages", [
        'title' => 'Where is my order?',
        'body' => 'Checking on your order.',
        'order' => $order->id,
    ]);

    expect(Conversation::sole()->order_id)->toBe($order->id);
});

it('opens a fresh thread every time, rather than finding one for the same customer', function (): void {
    $admin = $this->admin();
    $customer = $this->verifiedCustomer();
    $this->actingAs($admin, 'admin')->post("/admin/customers/{$customer->id}/messages", ['title' => 'First', 'body' => 'First message.']);

    $this->actingAs($admin, 'admin')->post("/admin/customers/{$customer->id}/messages", ['title' => 'Second', 'body' => 'Second message.']);

    expect(Conversation::count())->toBe(2);
});

it('refuses a message longer than the message limit', function (): void {
    $customer = $this->verifiedCustomer();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/customers/{$customer->id}/messages", ['title' => 'Order', 'body' => str_repeat('a', 2001)]);

    $response->assertSessionHasErrors('body');
    expect(Conversation::count())->toBe(0);
});

it('lets the admin message a blocked customer', function (): void {
    $admin = $this->admin();
    $customer = $this->verifiedCustomer();
    CustomerBlock::factory()->create(['customer_id' => $customer->id]);

    $show = $this->actingAs($admin, 'admin')->get("/admin/customers/{$customer->id}");
    $show->assertSee('name="body"', escape: false);

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/customers/{$customer->id}/messages", ['title' => 'About your standing', 'body' => 'The block stands until we hear back.']);

    $conversation = Conversation::sole();
    $response->assertRedirect(route('admin.messages.show', $conversation));
    expect(Message::where('conversation_id', $conversation->id)->count())->toBe(1);
});

it('answers not found for a customer id that matches nothing', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')
        ->post('/admin/customers/999999/messages', ['title' => 'Order', 'body' => 'We looked into your order.']);

    $response->assertNotFound();
    expect(Conversation::count())->toBe(0);
});

it('trips the conversation-open limit, handing the customer page back with the message still in the box', function (): void {
    Config::set('rate_limits.conversation_open', RateLimitValue::parse('1/1h', 'RATE_LIMIT_CONVERSATION_OPEN'));
    $admin = $this->admin();
    $firstCustomer = $this->verifiedCustomer();
    $secondCustomer = $this->verifiedCustomer();
    $this->actingAs($admin, 'admin')->post("/admin/customers/{$firstCustomer->id}/messages", ['title' => 'First', 'body' => 'First message.']);

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/customers/{$secondCustomer->id}/messages", ['title' => 'Second', 'body' => 'Second message.']);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
    $response->assertSee('Message customer');
    $response->assertSee('>Second message.</textarea>', escape: false);
    expect(Conversation::count())->toBe(1);
});
