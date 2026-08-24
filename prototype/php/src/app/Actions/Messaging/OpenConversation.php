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
        $story = Story::for(StoryEvent::ConversationOpen)->will('opening a conversation', [
            'kind' => $subject->kind->value,
        ]);

        $conversation = Conversation::openFor($subject, $now);

        $story->did('opened the conversation', [
            'conversation_id' => $conversation->id,
            'kind' => $conversation->kind->value,
            'listing_id' => $conversation->listing_id,
            'fulfillment_id' => $conversation->fulfillment_id,
        ]);

        return $conversation;
    }
}
