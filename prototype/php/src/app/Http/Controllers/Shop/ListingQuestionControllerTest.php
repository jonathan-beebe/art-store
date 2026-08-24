<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingStatus;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Models\Message;
use App\Notifications\MessageReceived;
use App\Support\CustomerIdentity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;

it('lets a visitor who has never signed in ask and land on the thread', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['slug' => 'harbour-at-dawn']);

    $response = $this->post('/art/harbour-at-dawn/questions', ['body' => 'Does this ship framed?']);

    $conversation = Conversation::sole();
    $asker = Customer::sole();
    expect($conversation->seller_id)->toBe($seller->id)
        ->and($conversation->customer_id)->toBe($asker->id)
        ->and($conversation->listing_id)->toBe($listing->id)
        ->and(Message::sole()->body)->toBe('Does this ship framed?');
    $response->assertRedirect(route('shop.messages.show', $conversation));
    // The identity the question hangs on is carried forward to the next visit.
    $response->assertCookie(CustomerIdentity::COOKIE, (string) $asker->id);
});

it('tells the seller a question is waiting', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $this->visitor();
    Notification::fake();

    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Does this ship framed?']);

    Notification::assertSentTo(
        $seller,
        MessageReceived::class,
        fn (MessageReceived $notification): bool => $notification->toArray($seller)['url']
            === route('seller.messages.show', Conversation::sole()),
    );
});

it('finds the same thread on a second question about the same listing', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['slug' => 'harbour-at-dawn']);
    $visitor = $this->visitor();

    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Does this ship framed?']);
    $this->post('/art/harbour-at-dawn/questions', ['body' => 'And is it signed?']);

    expect(Conversation::count())->toBe(1)
        ->and(Message::where('conversation_id', Conversation::sole()->id)->count())->toBe(2)
        ->and(Conversation::sole()->customer_id)->toBe($visitor->id);
});

it('refuses an empty question and opens no thread', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->post('/art/harbour-at-dawn/questions', ['body' => '']);

    $response->assertSessionHasErrors('body');
    expect(Conversation::count())->toBe(0);
});

it('answers not found for a listing not on the storefront', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'sketchbook', 'status' => ListingStatus::Draft]);

    $response = $this->post('/art/sketchbook/questions', ['body' => 'Is this for sale yet?']);

    $response->assertNotFound();
    expect(Conversation::count())->toBe(0);
});

it('opens the thread but refuses the question while blocked', function (): void {
    $visitor = $this->visitor();
    CustomerBlock::factory()->create(['customer_id' => $visitor->id]);
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    Notification::fake();

    $response = $this->post('/art/harbour-at-dawn/questions', ['body' => 'Still available?']);

    $response->assertForbidden();
    expect(Message::count())->toBe(0);
    Notification::assertNothingSent();
});

it('opens no second thread when a blocked visitor asks again', function (): void {
    $visitor = $this->visitor();
    CustomerBlock::factory()->create(['customer_id' => $visitor->id]);
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Still available?']);
    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Hello?']);

    // The thread opens before the policy refuses the post, so a blocked
    // visitor leaves an empty one behind. The subject key is what keeps it
    // to one however many times they try.
    expect(Conversation::count())->toBe(1)
        ->and(Message::count())->toBe(0);
});

it('reads the empty thread as an inbox row with no preview on both sites', function (): void {
    $visitor = $this->visitor();
    CustomerBlock::factory()->create(['customer_id' => $visitor->id]);
    $seller = $this->seller('Blue Kiln Studio');
    $this->listing($seller, ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Still available?']);

    $visitorInbox = $this->get('/messages');
    $sellerInbox = $this->actingAs($seller, 'seller')->get('/seller/messages');

    $visitorInbox->assertOk();
    $visitorInbox->assertSee('Blue Kiln Studio');
    $visitorInbox->assertSee('Harbour at Dawn');
    $sellerInbox->assertOk();
    $sellerInbox->assertSee("Customer {$visitor->id}");
    $sellerInbox->assertSee('Harbour at Dawn');
    $sellerInbox->assertDontSee('unread');
});

it('finds the anonymous thread on the verified account after the asker verifies', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $verifiedInAdvance = Customer::factory()->create(['email' => 'shopper@example.com']);
    $anonymous = $this->visitor();

    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Does this ship framed?']);
    $conversationBeforeVerification = Conversation::sole();

    $this->post('/login', ['email' => 'shopper@example.com']);
    $magicLink = Arr::string(Session::all(), 'debug_magic_link');
    $this->get($magicLink);
    $verified = Customer::where('email', 'shopper@example.com')->sole();

    $response = $this->get('/messages');

    $response->assertSee('Harbour at Dawn');
    $conversation = Conversation::sole();
    $message = Message::sole();
    expect($conversation->id)->toBe($conversationBeforeVerification->id)
        ->and($conversation->customer_id)->toBe($verified->id)
        ->and($verified->id)->toBe($verifiedInAdvance->id)
        ->and($verified->id)->not->toBe($anonymous->id)
        ->and($message->sender_id)->toBe($verified->id)
        ->and($message->sender_type)->toBe('customer')
        // The visitor's own question was theirs, so it must not read back as
        // unread now that it belongs to the verified account.
        ->and(Message::query()->unreadBy($verified)->count())->toBe(0);
});

it('carries the question to the seller and the answer back to the visitor', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $this->visitor();
    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Does this ship framed?']);
    $conversation = Conversation::sole();

    $sellerThread = $this->actingAs($seller, 'seller')->get(route('seller.messages.show', $conversation));
    $this->actingAs($seller, 'seller')
        ->post(route('seller.messages.store', $conversation), ['body' => 'Yes, in black wood.']);

    $sellerThread->assertSee('Does this ship framed?');
    $this->get('/')->assertSee('Messages (1)', escape: false);
    $this->get(route('shop.messages.show', $conversation))->assertSee('Yes, in black wood.');
    $this->get('/')->assertDontSee('Messages (1)', escape: false);
});

it('trips the conversation-open limit, handing the listing back with the question still in the box', function (): void {
    Config::set('rate_limits.conversation_open', RateLimitValue::parse('1/1h', 'RATE_LIMIT_CONVERSATION_OPEN'));
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    $this->listing($this->seller('Rye Press'), ['slug' => 'winter-elm']);
    $this->visitor();
    $this->post('/art/harbour-at-dawn/questions', ['body' => 'Does this ship framed?']);

    $response = $this->post('/art/winter-elm/questions', ['body' => 'Is this signed?']);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
    $response->assertSee('Ask the seller a question');
    $response->assertSee('>Is this signed?</textarea>', escape: false);
    expect(Conversation::count())->toBe(1);
});
