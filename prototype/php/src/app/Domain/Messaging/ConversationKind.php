<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Domain\Auth\ActorType;

/**
 * Four pairings share one message store. Every kind has exactly two
 * participants, which is what makes one `read_at` per message unambiguous:
 * the reader is always the participant who did not send it. On the two
 * support kinds one side is the desk — every admin, collectively — rather
 * than one admin row, so `admin_id` records who first answered rather than
 * gating participation.
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
     * The `conversations` column naming the subject a fulfillment thread's
     * `subject_key` is keyed by — the one kind that finds rather than opens.
     */
    public function subjectColumn(): ?string
    {
        return $this === self::Fulfillment ? 'fulfillment_id' : null;
    }

    /**
     * The `conversations` column(s) a fresh thread of this kind may carry
     * beyond its two participants — what the row is about, or (for the two
     * support kinds) which order it was raised over.
     *
     * @return list<string>
     */
    public function contextColumns(): array
    {
        return match ($this) {
            self::AdminSeller, self::Fulfillment => ['fulfillment_id'],
            self::AdminCustomer => ['order_id'],
            self::ListingQuestion => ['listing_id'],
        };
    }

    public function admits(ActorType $actor): bool
    {
        return in_array($actor->participantColumn(), $this->participantColumns(), true);
    }

    /**
     * A fresh thread every time an actor asks for one, versus the one
     * fulfillment thread an order's two sides share — the find-or-open shape
     * `subject_key` exists to serve.
     */
    public function opensFresh(): bool
    {
        return $this !== self::Fulfillment;
    }

    /**
     * The two support kinds, where one side is every admin collectively
     * rather than one participant row.
     */
    public function isDesk(): bool
    {
        return match ($this) {
            self::AdminSeller, self::AdminCustomer => true,
            self::Fulfillment, self::ListingQuestion => false,
        };
    }

    /**
     * The side that may mark a thread of this kind resolved: the desk on the
     * two support kinds, the seller on the two the seller answers. A customer
     * never resolves; they reopen by replying.
     */
    public function resolvableBy(ActorType $actor): bool
    {
        return match ($this) {
            self::AdminSeller, self::AdminCustomer => $actor === ActorType::Admin,
            self::Fulfillment, self::ListingQuestion => $actor === ActorType::Seller,
        };
    }

    /**
     * What a notification or an inbox row says the thread is about.
     */
    public function topic(?string $orderId, ?string $listingTitle): string
    {
        return match ($this) {
            self::AdminSeller, self::AdminCustomer => 'Support',
            self::Fulfillment => "Order {$orderId}",
            self::ListingQuestion => $listingTitle ?? 'a listing',
        };
    }
}
