<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Auth\ActorType;
use App\Domain\DomainRuleViolation;
use App\Domain\Messaging\ConversationStatus;
use App\Logging\StoryEvent;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Seller;
use App\Notifications\ConversationResolved as ConversationResolvedNotification;
use App\Support\Story;
use DateTimeImmutable;

/**
 * Marks a thread resolved: the seller on the two kinds a seller answers, the
 * desk on the two support kinds. The supported side hears about it with
 * "Reply to reopen" — the whole escape hatch, so nobody is locked out of a
 * thread.
 */
final readonly class ResolveConversation
{
    public function __invoke(Conversation $conversation, Seller|Admin $resolver, DateTimeImmutable $now): Conversation
    {
        return Story::for(StoryEvent::ConversationResolve)->tell('resolving a conversation', [
            'conversation_id' => $conversation->id,
        ], function (Story $story) use ($conversation, $resolver, $now): Conversation {
            if (ConversationStatus::of($conversation->resolved_at) === ConversationStatus::Resolved) {
                throw new DomainRuleViolation('This thread is already resolved.');
            }

            $conversation->update([
                'resolved_at' => $now,
                'resolved_by_type' => $resolver->getMorphClass(),
                'resolved_by_id' => $resolver->id,
            ]);

            $story->did('resolved the conversation', [
                'conversation_id' => $conversation->id,
                'resolved_by_type' => $resolver->getMorphClass(),
                'resolved_by_id' => $resolver->id,
            ]);

            $this->notifySupportedSide($conversation);

            return $conversation;
        });
    }

    /**
     * The side that was not resolving it: the seller or customer on a desk
     * thread, the customer on the two kinds a seller answers. Read through
     * `counterpart`, the same lookup a thread header uses.
     */
    private function notifySupportedSide(Conversation $conversation): void
    {
        $conversation->loadMissing(['seller', 'customer', 'listing', 'fulfillment']);

        $resolvingType = $conversation->kind->isDesk() ? ActorType::Admin : ActorType::Seller;
        $supported = $conversation->counterpart($resolvingType);

        if ($supported === null) {
            return;
        }

        $supportedType = ActorType::from($supported->getMorphClass());
        $topic = $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title);
        $url = route($supportedType->conversationRouteName(), $conversation);

        $supported->notify(new ConversationResolvedNotification($topic, $url));
    }
}
