<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\ConversationSubject;
use App\Logging\StoryEvent;
use App\Models\Conversation;
use App\Support\Story;
use DateTimeImmutable;

final readonly class OpenConversation
{
    public function __invoke(ConversationSubject $subject, DateTimeImmutable $now): Conversation
    {
        return Story::for(StoryEvent::ConversationOpen)->tell('opening a conversation', [
            'kind' => $subject->kind->value,
        ], function (Story $story) use ($subject, $now): Conversation {
            $conversation = Conversation::openFor($subject, $now);

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
