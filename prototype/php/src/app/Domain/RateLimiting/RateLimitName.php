<?php

declare(strict_types=1);

namespace App\Domain\RateLimiting;

/**
 * The seven limits of docs/alignment.md §3. Each case names its own env
 * variable and default, so `config/rate_limits.php` reads them from here
 * instead of repeating the table as a second, driftable copy.
 */
enum RateLimitName: string
{
    case MagicLinkRequest = 'magic_link_request';
    case MagicLinkConsume = 'magic_link_consume';
    case MessagePost = 'message_post';
    case ConversationOpen = 'conversation_open';
    case Checkout = 'checkout';
    case PaymentAttempt = 'payment_attempt';
    case ListingWrite = 'listing_write';

    public function envVariable(): string
    {
        return match ($this) {
            self::MagicLinkRequest => 'RATE_LIMIT_MAGIC_LINK_REQUEST',
            self::MagicLinkConsume => 'RATE_LIMIT_MAGIC_LINK_CONSUME',
            self::MessagePost => 'RATE_LIMIT_MESSAGE_POST',
            self::ConversationOpen => 'RATE_LIMIT_CONVERSATION_OPEN',
            self::Checkout => 'RATE_LIMIT_CHECKOUT',
            self::PaymentAttempt => 'RATE_LIMIT_PAYMENT_ATTEMPT',
            self::ListingWrite => 'RATE_LIMIT_LISTING_WRITE',
        };
    }

    public function default(): string
    {
        return match ($this) {
            self::MagicLinkRequest => '5/15m',
            self::MagicLinkConsume => '20/15m',
            self::MessagePost => '30/1h',
            self::ConversationOpen => '10/1h',
            self::Checkout => '10/1h',
            self::PaymentAttempt => '5/15m',
            self::ListingWrite => '60/1h',
        };
    }
}
