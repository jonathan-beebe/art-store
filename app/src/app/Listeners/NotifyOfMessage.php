<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Auth\ActorType;
use App\Events\MessagePosted;
use App\Models\Conversation;
use App\Notifications\MessageReceived;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Every participant who did not send a posted message hears about it, once
 * the post is committed — one seller or customer, or every admin at once
 * when the desk is the other side.
 */
final readonly class NotifyOfMessage implements ShouldHandleEventsAfterCommit
{
    public function handle(MessagePosted $event): void
    {
        $message = $event->message;
        $message->loadMissing(['conversation.seller', 'conversation.customer', 'conversation.admin', 'conversation.listing', 'conversation.fulfillment']);
        $conversation = $message->conversation;

        $topic = $this->topicFor($conversation);

        foreach ($conversation->recipientsOf($message) as $recipient) {
            $recipientType = ActorType::from($recipient->getMorphClass());
            $url = route($recipientType->conversationRouteName(), $conversation);

            $recipient->notify(new MessageReceived($topic, $url));
        }
    }

    /**
     * What a support notification says a thread is about is the desk topic
     * plus its title, when it carries one — "Support · Payout timing".
     */
    private function topicFor(Conversation $conversation): string
    {
        $topic = $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title);

        return $conversation->kind->isDesk() && $conversation->title !== null
            ? "{$topic} · {$conversation->title}"
            : $topic;
    }
}
