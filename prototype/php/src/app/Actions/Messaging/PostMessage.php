<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\MessageBody;
use App\Events\MessagePosted;
use App\Logging\StoryEvent;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Seller;
use App\Support\Story;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class PostMessage
{
    public function __invoke(Conversation $conversation, Seller|Customer|Admin $sender, MessageBody $body, DateTimeImmutable $now): Message
    {
        $story = Story::for(StoryEvent::MessagePost)->will('posting a message to a conversation', [
            'conversation_id' => $conversation->id,
            'sender_type' => $sender->getMorphClass(),
            'sender_id' => $sender->id,
        ]);

        $message = DB::transaction(function () use ($conversation, $sender, $body, $now): Message {
            $message = $conversation->messages()->create([
                'sender_type' => $sender->getMorphClass(),
                'sender_id' => $sender->id,
                'body' => $body->value,
                'sent_at' => $now,
            ]);

            $conversation->update(['last_message_at' => $now]);

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
    }
}
