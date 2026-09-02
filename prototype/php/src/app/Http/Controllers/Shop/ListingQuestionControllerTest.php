<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\GenerateVariants;
use App\Domain\Listings\ListingStatus;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Conversation;
use App\Models\CustomerBlock;
use App\Models\Message;
use App\Notifications\MessageReceived;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

it('redirects a signed-out visitor to sign in rather than opening a thread', function (): void {
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->post('/art/harbour-at-dawn/questions', ['body' => 'Does this ship framed?']);

    $response->assertRedirect(route('auth.customer.login'));
    expect(Conversation::count())->toBe(0);
});

it('lets a verified customer ask and land on the thread, titled from the question', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['slug' => 'harbour-at-dawn']);
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');

    $response = $this->post('/art/harbour-at-dawn/questions', ['body' => 'Does this ship framed?']);

    $conversation = Conversation::sole();
    expect($conversation->seller_id)->toBe($seller->id)
        ->and($conversation->customer_id)->toBe($customer->id)
        ->and($conversation->listing_id)->toBe($listing->id)
        ->and($conversation->title)->toBe('Does this ship framed?')
        ->and(Message::sole()->body)->toBe('Does this ship framed?');
    $response->assertRedirect(route('shop.messages.show', $conversation));
});

it('tells the seller a question is waiting', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    Notification::fake();

    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Does this ship framed?']);

    Notification::assertSentTo(
        $seller,
        MessageReceived::class,
        fn (MessageReceived $notification): bool => $notification->toArray($seller)['url']
            === route('seller.messages.show', Conversation::sole()),
    );
});

it('opens a fresh thread for a second question about the same listing, one question per thread', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['slug' => 'harbour-at-dawn']);
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');

    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Does this ship framed?']);
    $this->post('/art/harbour-at-dawn/questions', ['body' => 'And is it signed?']);

    expect(Conversation::count())->toBe(2)
        ->and(Conversation::query()->pluck('customer_id')->unique()->all())->toBe([$customer->id]);
});

it('refuses an empty question and opens no thread', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->post('/art/harbour-at-dawn/questions', ['body' => '']);

    $response->assertSessionHasErrors('body');
    expect(Conversation::count())->toBe(0);
});

it('answers not found for a listing not on the storefront', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    $this->listing($this->seller(), ['slug' => 'sketchbook', 'status' => ListingStatus::Draft]);

    $response = $this->post('/art/sketchbook/questions', ['body' => 'Is this for sale yet?']);

    $response->assertNotFound();
    expect(Conversation::count())->toBe(0);
});

it('refuses the question while blocked and opens no thread', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    CustomerBlock::factory()->create(['customer_id' => $customer->id]);
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    Notification::fake();

    $response = $this->post('/art/harbour-at-dawn/questions', ['body' => 'Still available?']);

    $response->assertForbidden();
    // The thread and its first message are one transaction, so the refusal
    // takes the thread with it rather than leaving an empty row in two
    // inboxes.
    expect(Conversation::count())->toBe(0)
        ->and(Message::count())->toBe(0);
    Notification::assertNothingSent();
});

it('lets the customer ask once the block is lifted', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    $block = CustomerBlock::factory()->create(['customer_id' => $customer->id]);
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Still available?']);
    $block->update(['lifted_at' => now()]);
    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Still available?']);

    expect(Conversation::count())->toBe(1)
        ->and(Message::sole()->body)->toBe('Still available?');
});

it('carries the question to the seller and the answer back to the customer', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Does this ship framed?']);
    $conversation = Conversation::sole();

    $sellerThread = $this->actingAs($seller, 'seller')->get(route('seller.messages.show', $conversation));
    $this->actingAs($seller, 'seller')
        ->post(route('seller.messages.store', $conversation), ['body' => 'Yes, in black wood.']);

    $sellerThread->assertSee('Does this ship framed?');
    $this->actingAs($customer, 'customer')->get('/')->assertSee('Messages (1)', escape: false);
    $this->actingAs($customer, 'customer')->get(route('shop.messages.show', $conversation))->assertSee('Yes, in black wood.');
    $this->actingAs($customer, 'customer')->get('/')->assertDontSee('Messages (1)', escape: false);
});

it('trips the conversation-open limit, handing the listing back with the question still in the box', function (): void {
    Config::set('rate_limits.conversation_open', RateLimitValue::parse('1/1h', 'RATE_LIMIT_CONVERSATION_OPEN'));
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    $this->listing($this->seller('Rye Press'), ['slug' => 'winter-elm']);
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Does this ship framed?']);

    $response = $this->post('/art/winter-elm/questions', ['body' => 'Is this signed?']);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
    $response->assertSee('Made by Rye Press');
    $response->assertSee('>Is this signed?</textarea>', escape: false);
    expect(Conversation::count())->toBe(1);
});

it('re-renders a configured listing on the rate limit with its configurator and highlights intact', function (): void {
    Config::set('rate_limits.conversation_open', RateLimitValue::parse('1/1h', 'RATE_LIMIT_CONVERSATION_OPEN'));
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    $listing = $this->listing($this->seller('Rye Press'), ['slug' => 'ring']);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    app(GenerateVariants::class)($listing);
    $this->attribute($listing, 'Material', 'Sterling Silver');
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->actingAs($customer, 'customer');
    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Does this ship framed?']);

    $response = $this->post('/art/ring/questions', ['body' => 'Is this signed?']);

    $response->assertStatus(429);
    $response->assertSee('Metal');
    $response->assertSee('Material');
    $response->assertSee('Sterling Silver');
});
