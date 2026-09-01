<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Messaging\ConversationSubject;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Conversation;
use App\Models\CustomerBlock;
use App\Models\Message;
use Illuminate\Support\Facades\Config;

it('opens the customers admin thread, posts the message, and lands on it', function (): void {
    $admin = $this->admin();
    $customer = $this->verifiedCustomer();

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/customers/{$customer->id}/messages", ['body' => 'We looked into your order.']);

    $conversation = Conversation::sole();
    expect($conversation->subject_key)
        ->toBe(ConversationSubject::adminCustomer($admin->id, $customer->id)->subjectKey());
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'We looked into your order.')->exists())->toBeTrue();
    $response->assertRedirect(route('admin.messages.show', $conversation));
});

it('lands on the same thread a second time', function (): void {
    $admin = $this->admin();
    $customer = $this->verifiedCustomer();
    $this->actingAs($admin, 'admin')->post("/admin/customers/{$customer->id}/messages", ['body' => 'First message.']);

    $this->actingAs($admin, 'admin')->post("/admin/customers/{$customer->id}/messages", ['body' => 'Second message.']);

    expect(Conversation::count())->toBe(1)
        ->and(Message::where('conversation_id', Conversation::sole()->id)->count())->toBe(2);
});

it('refuses a message longer than the message limit', function (): void {
    $customer = $this->verifiedCustomer();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/customers/{$customer->id}/messages", ['body' => str_repeat('a', 2001)]);

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
        ->post("/admin/customers/{$customer->id}/messages", ['body' => 'The block stands until we hear back.']);

    $conversation = Conversation::sole();
    $response->assertRedirect(route('admin.messages.show', $conversation));
    expect(Message::where('conversation_id', $conversation->id)->count())->toBe(1);
});

it('answers not found for a customer id that matches nothing', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')
        ->post('/admin/customers/999999/messages', ['body' => 'We looked into your order.']);

    $response->assertNotFound();
    expect(Conversation::count())->toBe(0);
});

it('trips the message-post limit, handing the customer page back with the message still in the box', function (): void {
    Config::set('rate_limits.message_post', RateLimitValue::parse('1/1h', 'RATE_LIMIT_MESSAGE_POST'));
    $admin = $this->admin();
    $firstCustomer = $this->verifiedCustomer();
    $secondCustomer = $this->verifiedCustomer();
    $this->actingAs($admin, 'admin')->post("/admin/customers/{$firstCustomer->id}/messages", ['body' => 'First message.']);

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/customers/{$secondCustomer->id}/messages", ['body' => 'Second message.']);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
    $response->assertSee('Message customer');
    $response->assertSee('>Second message.</textarea>', escape: false);
    expect(Conversation::count())->toBe(1);
});
