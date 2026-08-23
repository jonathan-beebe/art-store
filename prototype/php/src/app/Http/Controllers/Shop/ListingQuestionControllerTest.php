<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingStatus;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Models\Message;
use Illuminate\Support\Arr;
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

    $response = $this->post('/art/harbour-at-dawn/questions', ['body' => 'Still available?']);

    $response->assertForbidden();
    expect(Message::count())->toBe(0);
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
