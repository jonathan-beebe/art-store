<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Messaging\ConversationKind;
use App\Domain\Messaging\ConversationStatus;
use App\Domain\Seller\SupportThreadRow;
use App\Models\Conversation;
use App\Models\Seller;

/**
 * The seller's own support threads, newest first — the support hub's own
 * list, and the same threads Messages' Support tab lists.
 */
final class SupportThreads
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return list<SupportThreadRow>
     */
    public static function for(Seller $seller): array
    {
        return array_values(Conversation::query()
            ->withParticipant($seller)
            ->ofKind(ConversationKind::AdminSeller)
            ->with('latestMessage')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(self::toRow(...))
            ->all());
    }

    private static function toRow(Conversation $conversation): SupportThreadRow
    {
        return new SupportThreadRow(
            $conversation->id,
            $conversation->title ?? '',
            $conversation->latestMessage?->body,
            $conversation->status() === ConversationStatus::Resolved,
        );
    }
}
