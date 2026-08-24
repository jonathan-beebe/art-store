<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Domain\Auth\ActorType;

/**
 * Four pairings share one message store. Every kind has exactly two
 * participants, which is what makes one `read_at` per message unambiguous:
 * the reader is always the participant who did not send it.
 */
enum ConversationKind: string
{
    case AdminSeller = 'admin_seller';
    case AdminCustomer = 'admin_customer';
    case Fulfillment = 'fulfillment';
    case ListingQuestion = 'listing_question';

    /**
     * The two `conversations` columns this kind fills.
     *
     * @return array{0: string, 1: string}
     */
    public function participantColumns(): array
    {
        return match ($this) {
            self::AdminSeller => ['admin_id', 'seller_id'],
            self::AdminCustomer => ['admin_id', 'customer_id'],
            self::Fulfillment, self::ListingQuestion => ['seller_id', 'customer_id'],
        };
    }

    /**
     * The `conversations` column naming what the thread is about, or null for
     * the two support kinds, which need no subject beyond their participants.
     */
    public function subjectColumn(): ?string
    {
        return match ($this) {
            self::Fulfillment => 'fulfillment_id',
            self::ListingQuestion => 'listing_id',
            self::AdminSeller, self::AdminCustomer => null,
        };
    }

    public function admits(ActorType $actor): bool
    {
        return in_array($actor->participantColumn(), $this->participantColumns(), true);
    }

    /**
     * What a notification or an inbox row says the thread is about.
     */
    public function topic(?int $orderId, ?string $listingTitle): string
    {
        return match ($this) {
            self::AdminSeller, self::AdminCustomer => 'Support',
            self::Fulfillment => "Order #{$orderId}",
            self::ListingQuestion => $listingTitle ?? 'a listing',
        };
    }
}
