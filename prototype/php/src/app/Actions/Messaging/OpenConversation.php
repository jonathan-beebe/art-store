<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\ConversationSubject;
use App\Domain\Messaging\ThreadOpening;
use App\Logging\StoryEvent;
use App\Models\Conversation;
use App\Support\Story;
use DateTimeImmutable;

/**
 * Inserts a thread with no message yet, or finds the one a fulfillment
 * subject already opened. A fulfillment thread reaches an empty page this
 * way, since the actor types the first message on the page they land on; a
 * fresh-opened thread reaches one this way only where no first message
 * exists yet to compose it with — `OpenThread` is how the other three kinds
 * open with the message that names them.
 */
final readonly class OpenConversation
{
    public function __invoke(ConversationSubject|ThreadOpening $subject, DateTimeImmutable $now): Conversation
    {
        return Story::for(StoryEvent::ConversationOpen)->tell('opening a conversation', [
            'kind' => $subject->kind->value,
        ], function (Story $story) use ($subject, $now): Conversation {
            $conversation = $subject instanceof ConversationSubject
                ? Conversation::openFor($subject, $now)
                : Conversation::create([...$subject->columns(), 'last_message_at' => $now]);

            $story->did('opened the conversation', [
                'conversation_id' => $conversation->id,
                'kind' => $conversation->kind->value,
                'listing_id' => $conversation->listing_id,
                'fulfillment_id' => $conversation->fulfillment_id,
            ]);

            return $conversation;
        });
    }
}
