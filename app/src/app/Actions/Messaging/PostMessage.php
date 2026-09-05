<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Auth\ActorType;
use App\Domain\DomainRuleViolation;
use App\Domain\Messaging\ConversationStatus;
use App\Domain\Messaging\MessageBody;
use App\Events\MessagePosted;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class PostMessage
{
    public function __invoke(
        Conversation $conversation,
        Seller|Customer|Admin $sender,
        MessageBody $body,
        DateTimeImmutable $now,
        ?Message $replyTo = null,
    ): Message {
        return Story::for(StoryEvent::MessagePost)->tell('posting a message to a conversation', [
            'conversation_id' => $conversation->id,
            'sender_type' => $sender->getMorphClass(),
            'sender_id' => $sender->id,
        ], function (Story $story) use ($conversation, $sender, $body, $now, $replyTo): Message {
            if ($replyTo !== null && $replyTo->conversation_id !== $conversation->id) {
                throw new DomainRuleViolation('A reply must belong to the same thread.');
            }

            $message = DB::transaction(function () use ($conversation, $sender, $body, $now, $replyTo): Message {
                $message = $conversation->messages()->create([
                    'sender_type' => $sender->getMorphClass(),
                    'sender_id' => $sender->id,
                    'reply_to_message_id' => $replyTo?->id,
                    'body' => $body->value,
                    'sent_at' => $now,
                ]);

                $conversation->update([
                    'last_message_at' => $now,
                    ...$this->handledByUpdate($conversation, $sender),
                    ...$this->reopenUpdate($conversation, $sender),
                ]);

                MessagePosted::dispatch($message, $now);

                return $message;
            });

            $story->did('posted the message', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'sender_type' => $sender->getMorphClass(),
                'sender_id' => $sender->id,
            ]);

            return $message;
        });
    }

    /**
     * The first admin reply on a desk thread names who is handling it —
     * `admin_id` records that, and only that; it never gates who may post.
     *
     * @return array<string, mixed>
     */
    private function handledByUpdate(Conversation $conversation, Seller|Customer|Admin $sender): array
    {
        return $sender instanceof Admin && $conversation->kind->isDesk() && $conversation->admin_id === null
            ? ['admin_id' => $sender->id]
            : [];
    }

    /**
     * `ConversationStatus::afterPostBy` is the one pure rule this applies: a
     * post from the side that could not have resolved the thread reopens it.
     *
     * @return array<string, mixed>
     */
    private function reopenUpdate(Conversation $conversation, Seller|Customer|Admin $sender): array
    {
        $actorType = ActorType::from($sender->getMorphClass());
        $before = ConversationStatus::of($conversation->resolved_at);
        $after = $before->afterPostBy($actorType, $conversation->kind);

        return $before === $after ? [] : ['resolved_at' => null, 'resolved_by_type' => null, 'resolved_by_id' => null];
    }
}
