<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\ConversationSubject;
use App\Domain\Messaging\MessageBody;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * A thread and the message that opens it, in one transaction. Every way into
 * the messaging centre that asks something — the listing question, the
 * admin's message to a seller or a customer — goes through here, because a
 * thread and its first message are one act: a sender the gate turns down
 * leaves no conversation row behind for an inbox to list with nothing in it.
 */
final readonly class OpenConversationWithMessage
{
    public function __construct(
        private OpenConversation $openConversation,
        private PostMessage $postMessage,
    ) {}

    public function __invoke(
        ConversationSubject $subject,
        Seller|Customer|Admin $sender,
        MessageBody $body,
        DateTimeImmutable $now,
    ): Conversation {
        return DB::transaction(function () use ($subject, $sender, $body, $now): Conversation {
            $conversation = ($this->openConversation)($subject, $now);

            // Inside the transaction that opened the thread, so the refusal
            // takes the thread with it. The sender is named rather than read
            // off a guard: the storefront resolves its visitor from a cookie
            // through middleware and signs nobody in.
            Gate::forUser($sender)->authorize('post', $conversation);

            ($this->postMessage)($conversation, $sender, $body, $now);

            return $conversation;
        });
    }
}
