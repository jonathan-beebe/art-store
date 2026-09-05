<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\MessageBody;
use App\Domain\Messaging\ThreadOpening;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * A fresh, titled thread and the message that opens it, in one transaction.
 * Every way into the messaging centre that asks something — a listing
 * question, a seller or an admin opening a support thread — goes through
 * here, because a thread and its first message are one act: a sender the
 * gate turns down leaves no conversation row behind for an inbox to list
 * with nothing in it.
 */
final readonly class OpenThread
{
    public function __construct(
        private OpenConversation $openConversation,
        private PostMessage $postMessage,
    ) {}

    public function __invoke(
        ThreadOpening $opening,
        Seller|Customer|Admin $sender,
        MessageBody $body,
        DateTimeImmutable $now,
    ): Conversation {
        return DB::transaction(function () use ($opening, $sender, $body, $now): Conversation {
            $conversation = ($this->openConversation)($opening, $now);

            // Inside the transaction that opened the thread, so the refusal
            // takes the thread with it. `Gate::forUser` takes the sender
            // explicitly, because the storefront resolves its visitor from
            // a cookie through middleware, without ever signing them in.
            Gate::forUser($sender)->authorize('post', $conversation);

            ($this->postMessage)($conversation, $sender, $body, $now);

            return $conversation;
        });
    }
}
