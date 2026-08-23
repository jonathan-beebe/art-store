<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Auth\ActorType;
use App\Events\MessagePosted;
use App\Notifications\MessageReceived;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * The participant who did not send a posted message hears about it, once the
 * post is committed.
 */
final readonly class NotifyOfMessage implements ShouldHandleEventsAfterCommit
{
    public function handle(MessagePosted $event): void
    {
        $message = $event->message;
        $message->loadMissing(['conversation.seller', 'conversation.customer', 'conversation.admin', 'conversation.listing', 'conversation.fulfillment']);
        $conversation = $message->conversation;

        $recipient = $conversation->otherParticipant($message);

        if ($recipient === null) {
            return;
        }

        $recipientType = ActorType::from($recipient->getMorphClass());
        $topic = $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title);
        $url = route($recipientType->conversationRouteName(), $conversation);

        $recipient->notify(new MessageReceived($topic, $url));
    }
}
