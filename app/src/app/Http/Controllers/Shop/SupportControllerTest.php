<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Messaging\ThreadTitle;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Config;

it('redirects a signed-out visitor to sign in rather than showing the form', function (): void {
    $response = $this->get('/support');

    $response->assertRedirect(route('auth.customer.login'));
});

it('renders the form with the visitors recent orders as options', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    $listing = $this->listing($this->seller(), ['title' => 'Harbour at Dusk']);
    $order = $this->orderFor($customer, $listing);

    $response = $this->get('/support');

    $response->assertOk();
    $response->assertSee('Talk to us');
    $response->assertSee($order->id);
    $response->assertSee('Harbour at Dusk');
});

it('says there are no open conversations yet', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');

    $response = $this->get('/support');

    $response->assertSee('None yet.');
});

it('lists the open conversations already waiting', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    Conversation::factory()->adminCustomer()->create([
        'customer_id' => $customer->id,
        'title' => ThreadTitle::of('Where is my order?')->value,
    ]);

    $response = $this->get('/support');

    $response->assertSee('Where is my order?');
});

it('preselects an order named in the query string', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    $order = $this->orderFor($customer, $this->listing($this->seller()));

    $response = $this->get("/support?order={$order->id}");

    $response->assertOk();
    $response->assertSee('selected', escape: false);
});

it('ignores an order in the query string that is not the visitors', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    $this->orderFor($customer, $this->listing($this->seller()));
    $stranger = $this->verifiedCustomer();
    $strangersOrder = $this->orderFor($stranger, $this->listing($this->seller()));

    $response = $this->get("/support?order={$strangersOrder->id}");

    $response->assertOk();
    $response->assertDontSee('selected', escape: false);
});

it('opens the admin/customer thread, titled and bodied from the form', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');

    $response = $this->post('/support', [
        'subject' => 'Where is my order?',
        'body' => 'The tracking has not moved since Tuesday.',
    ]);

    $conversation = Conversation::sole();
    expect($conversation->kind->value)->toBe('admin_customer')
        ->and($conversation->customer_id)->toBe($customer->id)
        ->and($conversation->title)->toBe('Where is my order?')
        ->and($conversation->order_id)->toBeNull()
        ->and(Message::sole()->body)->toBe('The tracking has not moved since Tuesday.');
    $response->assertRedirect(route('shop.messages.show', $conversation));
});

it('carries the named order onto the thread when it belongs to the visitor', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    $order = $this->orderFor($customer, $this->listing($this->seller()));

    $this->post('/support', [
        'subject' => 'Where is my order?',
        'body' => 'The tracking has not moved since Tuesday.',
        'order_id' => $order->id,
    ]);

    expect(Conversation::sole()->order_id)->toBe($order->id);
});

it('ignores an order that does not belong to the visitor rather than refusing', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    $stranger = $this->verifiedCustomer();
    $order = $this->orderFor($stranger, $this->listing($this->seller()));

    $response = $this->post('/support', [
        'subject' => 'Where is my order?',
        'body' => 'The tracking has not moved since Tuesday.',
        'order_id' => $order->id,
    ]);

    $response->assertRedirect(route('shop.messages.show', Conversation::sole()));
    expect(Conversation::sole()->order_id)->toBeNull();
});

it('refuses a blank subject or message and opens no thread', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');

    $response = $this->post('/support', ['subject' => '', 'body' => '']);

    $response->assertSessionHasErrors(['subject', 'body']);
    expect(Conversation::count())->toBe(0);
});

it('trips the conversation-open limit, handing the form back with the subject and message still in the boxes', function (): void {
    Config::set('rate_limits.conversation_open', RateLimitValue::parse('1/1h', 'RATE_LIMIT_CONVERSATION_OPEN'));
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    $this->post('/support', ['subject' => 'First', 'body' => 'First message.']);

    $response = $this->post('/support', ['subject' => 'Second', 'body' => 'Second message.']);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
    $response->assertSee('Second');
    $response->assertSee('>Second message.</textarea>', escape: false);
    expect(Conversation::count())->toBe(1);
});
