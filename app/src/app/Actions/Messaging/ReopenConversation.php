<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\DomainRuleViolation;
use App\Domain\Messaging\ConversationStatus;
use App\Logging\StoryEvent;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Seller;
use App\Support\Story;

/**
 * Reopens a thread the supporting side resolved by mistake, or wants to
 * follow up on themselves. A reply from the supported side reopens it too,
 * through `PostMessage`'s own rule — this is the deliberate, no-message
 * version of the same transition.
 */
final readonly class ReopenConversation
{
    public function __invoke(Conversation $conversation, Seller|Admin $reopener): Conversation
    {
        return Story::for(StoryEvent::ConversationReopen)->tell('reopening a conversation', [
            'conversation_id' => $conversation->id,
        ], function (Story $story) use ($conversation): Conversation {
            if (ConversationStatus::of($conversation->resolved_at) === ConversationStatus::Open) {
                throw new DomainRuleViolation('This thread is not resolved.');
            }

            $conversation->update([
                'resolved_at' => null,
                'resolved_by_type' => null,
                'resolved_by_id' => null,
            ]);

            $story->did('reopened the conversation', [
                'conversation_id' => $conversation->id,
            ]);

            return $conversation;
        });
    }
}
