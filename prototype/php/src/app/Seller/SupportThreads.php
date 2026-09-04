<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Messaging\ConversationKind;
use App\Models\Conversation;
use App\Models\Seller;

/**
 * The seller's own support threads, newest first — the support hub's own
 * list, and the same rows Messages' Support tab lists.
 */
final readonly class SupportThreads
{
    private function __construct(
        /** @var list<Conversation> */
        public array $threads,
    ) {}

    public static function for(Seller $seller): self
    {
        $threads = Conversation::query()
            ->withParticipant($seller)
            ->ofKind(ConversationKind::AdminSeller)
            ->with('latestMessage')
            ->orderByDesc('last_message_at')
            ->get()
            ->all();

        return new self(array_values($threads));
    }
}
