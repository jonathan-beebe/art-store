<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Actions\Cart\AddToCart;
use App\Actions\Messaging\MarkConversationRead;
use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;
use App\Models\Message;
use App\Notifications\OrderShipped;

it('counts the items the visitor is carrying', function (): void {
    $visitor = $this->visitor();
    app(AddToCart::class)(
        $visitor->currentCart(),
        $this->listing($this->seller(), ['quantity' => 3]),
        2,
        $this->moment('2026-08-20 08:00:00'),
    );

    $response = $this->get('/');

    $response->assertSee('Cart (2)');
});

it('counts the notifications the visitor has not read', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    $visitor->notify(new OrderShipped('ord_00000000000000000000000001', 'Royal Mail', 'RM1'));

    $response = $this->actingAs($visitor, 'customer')->get('/');

    $response->assertSee('Account (1)');
});

it('counts the messages across every thread the visitor has not read', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    $seller = $this->seller();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::listingQuestion($seller->id, $visitor->id, $this->listing($seller)->id))
        ->create();
    Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id]);
    Message::factory()->from($visitor)->create(['conversation_id' => $conversation->id]);

    $response = $this->actingAs($visitor, 'customer')->get('/');

    $response->assertSee('Messages (1)', escape: false);
});

it('drops the message count once the thread is marked read', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $visitor->id]);
    Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id]);
    app(MarkConversationRead::class)($conversation, $visitor, $this->moment('2026-08-20 09:00:00'));

    $response = $this->actingAs($visitor, 'customer')->get('/');

    $response->assertDontSee('Messages (1)', escape: false);
});

it('carries the counts onto every storefront page without the controller passing them', function (): void {
    $this->visitor();

    $this->get('/cart')->assertSee('Cart (0)');
    $this->get('/favorites')->assertSee('Cart (0)');
    $this->get('/orders')->assertSee('Cart (0)');
});

it('renders a page with no storefront visitor without the counts', function (): void {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertDontSee('Cart (');
});
