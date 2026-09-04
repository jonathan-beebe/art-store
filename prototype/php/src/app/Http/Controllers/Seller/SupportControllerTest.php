<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\OpenThread;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Messaging\MessageBody;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\Messaging\ThreadTitle;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Conversation;
use App\Models\Fulfillment;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Facades\Config;

it('refuses a signed-out visitor to the support hub', function (): void {
    $this->get('/seller/support')->assertRedirect('/seller/login');
});

it('shows the desk, one entry per seeded admin', function (): void {
    $this->seed(AdminSeeder::class);
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/support');

    $response->assertOk();
    foreach (AdminSeeder::ADMINS as $admin) {
        $response->assertSee($admin['name']);
    }
});

it('IMPRV-030 renders a bracketed booking url as plain text, not a link', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/support');

    $response->assertOk();
    $response->assertDontSee('<a href="[', escape: false);
    $response->assertDontSee((string) config('support.booking_url'));
    $response->assertSee('Not published yet.');
});

it('IMPRV-030 renders an empty booking url as plain text, not a link with no href', function (): void {
    Config::set('support.booking_url', '');
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/support');

    $response->assertOk();
    $response->assertDontSee('href=""', escape: false);
    $response->assertSee('Not published yet.');
});

it('IMPRV-030 renders a bracketed phone number as plain text', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/support');

    $response->assertOk();
    $response->assertDontSee((string) config('support.phone'));
    $response->assertSee('Not published yet.');
});

it('IMPRV-030 renders an empty phone number as plain text', function (): void {
    Config::set('support.phone', '');
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/support');

    $response->assertOk();
    $response->assertSee('Not published yet.');
});

it('IMPRV-030 renders a configured phone number and booking url as themselves', function (): void {
    Config::set('support.phone', '+44 20 7946 0958');
    Config::set('support.booking_url', 'https://example.com/book');
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/support');

    $response->assertOk();
    $response->assertSee('+44 20 7946 0958');
    $response->assertSee('<a href="https://example.com/book"', escape: false);
    $response->assertDontSee('Not published yet.');
});

it('lists the help articles by their group', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/support');

    $response->assertOk();
    $response->assertSee('Getting paid');
    $response->assertSee('When money reaches your account');
});

it('lists the sellers own support threads', function (): void {
    $seller = $this->seller();
    app(OpenThread::class)(
        ThreadOpening::adminSeller($seller->id, ThreadTitle::of('A payout question')),
        $seller,
        MessageBody::of('Where did last week\'s payout go?'),
        $this->moment('2026-08-19 09:00:00'),
    );

    $response = $this->actingAs($seller, 'seller')->get('/seller/support');

    $response->assertOk();
    $response->assertSee('A payout question');
});

it('links start a conversation to the new-conversation form', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/support');

    $response->assertOk();
    $response->assertSee(route('seller.support.create'), escape: false);
});

it('renders the new conversation form with the sellers recent orders to pick from', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();

    $response = $this->actingAs($seller, 'seller')->get('/seller/support/new');

    $response->assertOk();
    $response->assertSee($fulfillment->id);
});

it('opens a titled admin/seller thread with the first message and lands on it', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->post('/seller/support/new', [
        'title' => 'Payout timing for August',
        'body' => 'My payout for the week of the 18th has not appeared.',
    ]);

    $conversation = Conversation::sole();
    expect($conversation->kind->value)->toBe('admin_seller')
        ->and($conversation->seller_id)->toBe($seller->id)
        ->and($conversation->admin_id)->toBeNull()
        ->and($conversation->title)->toBe('Payout timing for August')
        ->and($conversation->fulfillment_id)->toBeNull();
    $response->assertRedirect(route('seller.messages.show', $conversation));
    expect($conversation->messages()->sole()->body)->toBe('My payout for the week of the 18th has not appeared.');
});

it('carries the chosen order as the threads context', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();

    $this->actingAs($seller, 'seller')->post('/seller/support/new', [
        'title' => 'A question about this order',
        'body' => 'Was this shipped yet?',
        'fulfillment_id' => $fulfillment->id,
    ]);

    expect(Conversation::sole()->fulfillment_id)->toBe($fulfillment->id);
});

it('refuses another sellers fulfillment as the threads context', function (): void {
    $seller = $this->seller();
    $otherSeller = $this->seller('Other Studio');
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($otherSeller));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $othersFulfillment = Fulfillment::where('seller_id', $otherSeller->id)->sole();

    $response = $this->actingAs($seller, 'seller')->post('/seller/support/new', [
        'title' => 'A question about this order',
        'body' => 'Was this shipped yet?',
        'fulfillment_id' => $othersFulfillment->id,
    ]);

    $response->assertSessionHasErrors('fulfillment_id');
    expect(Conversation::count())->toBe(0);
});

it('opens a fresh thread every visit, rather than finding one already open', function (): void {
    $seller = $this->seller();
    $this->actingAs($seller, 'seller')->post('/seller/support/new', ['title' => 'First issue', 'body' => 'Body one.']);

    $this->actingAs($seller, 'seller')->post('/seller/support/new', ['title' => 'Second issue', 'body' => 'Body two.']);

    expect(Conversation::count())->toBe(2);
});

it('requires a title and a message', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->post('/seller/support/new', []);

    $response->assertSessionHasErrors(['title', 'body']);
    expect(Conversation::count())->toBe(0);
});

it('trips the conversation-open limit, handing the form back with the fields still filled', function (): void {
    Config::set('rate_limits.conversation_open', RateLimitValue::parse('1/1h', 'RATE_LIMIT_CONVERSATION_OPEN'));
    $seller = $this->seller();
    $this->actingAs($seller, 'seller')->post('/seller/support/new', ['title' => 'First issue', 'body' => 'Body one.']);

    $response = $this->actingAs($seller, 'seller')->post('/seller/support/new', ['title' => 'Second issue', 'body' => 'Body two.']);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
    $response->assertSee('value="Second issue"', escape: false);
    expect(Conversation::count())->toBe(1);
});
