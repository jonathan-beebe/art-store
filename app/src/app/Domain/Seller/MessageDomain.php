<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Messaging\ConversationKind;

/**
 * The seller inbox's `?domain=` (docs/messaging.md § "Inbox domains").
 */
enum MessageDomain: string
{
    case All = 'all';
    case Buyers = 'buyers';
    case Support = 'support';

    public static function default(): self
    {
        return self::All;
    }

    /**
     * The conversation kinds this domain narrows the inbox to; null is
     * every kind the seller participates in.
     *
     * @return list<ConversationKind>|null
     */
    public function kinds(): ?array
    {
        return match ($this) {
            self::Buyers => [ConversationKind::ListingQuestion, ConversationKind::Fulfillment],
            self::Support => [ConversationKind::AdminSeller],
            self::All => null,
        };
    }
}
