<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\MessageBody;
use App\Events\MessagePosted;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class PostMessage
{
    public function __invoke(Conversation $conversation, Seller|Customer|Admin $sender, MessageBody $body, DateTimeImmutable $now): Message
    {
        return DB::transaction(function () use ($conversation, $sender, $body, $now): Message {
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
    }
}
