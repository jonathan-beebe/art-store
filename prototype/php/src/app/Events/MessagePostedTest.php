<?php

declare(strict_types=1);

namespace App\Events;

use App\Actions\Messaging\PostMessage;
use App\Domain\Messaging\MessageBody;
use App\Models\Conversation;
use Illuminate\Support\Facades\Event;

it('is raised when a message is posted', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    Event::fake([MessagePosted::class]);

    $message = app(PostMessage::class)($conversation, $customer, MessageBody::of('Is this still available?'), $this->moment('2026-08-20 10:00:00'));

    Event::assertDispatched(
        MessagePosted::class,
        fn (MessagePosted $event): bool => $event->message->is($message)
            && $event->sentAt->format('Y-m-d H:i:s') === '2026-08-20 10:00:00',
    );
});
