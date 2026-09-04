<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

/**
 * What a seller has to do today, in four groups: parcels waiting to go
 * out, buyers waiting on a reply, the money settling for the next payout,
 * and listings that cannot sell as they stand. The rows come from the
 * queues each tool already reads ({@see \App\Seller\NeedsAttention});
 * this holds the heading each group wears at each count, the sentence it
 * shows when it holds nothing, and the age at which a parcel reads
 * urgent.
 */
final class AttentionQueue
{
    /** A parcel unshipped past this many days reads in red. */
    public const int SHIP_OVERDUE_DAYS = 2;

    private function __construct() {} // @codeCoverageIgnore

    /**
     * The four groups, in the order the dashboard renders them.
     *
     * @param  list<AttentionRow>  $toShip  oldest first
     * @param  list<AttentionRow>  $waiting
     * @param  list<AttentionRow>  $payout
     * @param  list<AttentionRow>  $listings
     * @return list<AttentionGroup>
     */
    public static function build(
        array $toShip,
        array $waiting,
        array $payout,
        array $listings,
        DateTimeImmutable $payoutDate,
        AttentionLinks $links,
    ): array {
        return [
            new AttentionGroup(
                icon: FeedIcon::Truck,
                title: self::counted(count($toShip), 'order to ship', 'orders to ship'),
                supporting: 'Oldest first. Buyers expect a parcel within three days.',
                actionLabel: 'Open orders',
                actionHref: $links->orders,
                rows: $toShip,
                emptySentence: 'Nothing is waiting to ship.',
            ),
            new AttentionGroup(
                icon: FeedIcon::Chat,
                title: self::counted(count($waiting), 'message waiting on you', 'messages waiting on you'),
                supporting: 'Buyers who wrote and have not heard back.',
                actionLabel: 'Open messages',
                actionHref: $links->messages,
                rows: $waiting,
                emptySentence: 'Every buyer has heard back from you.',
            ),
            new AttentionGroup(
                icon: FeedIcon::Cash,
                title: 'Payout '.$payoutDate->format('l, M j'),
                supporting: 'What has released so far, and what is still on its way.',
                actionLabel: 'See earnings',
                actionHref: $links->earnings,
                rows: $payout,
                emptySentence: 'Nothing has settled yet.',
            ),
            new AttentionGroup(
                icon: FeedIcon::Pencil,
                title: self::counted(count($listings), 'listing needs work', 'listings need work'),
                supporting: 'Drafts that cannot publish, and pieces that sold out.',
                actionLabel: 'Open listings',
                actionHref: $links->listings,
                rows: $listings,
                emptySentence: 'Every listing is published and in stock.',
            ),
        ];
    }

    /**
     * Whether a parcel placed at `$placedAt` has waited long enough for
     * its age to read in red.
     */
    public static function isOverdue(DateTimeImmutable $placedAt, DateTimeImmutable $now): bool
    {
        return $placedAt->modify('+'.self::SHIP_OVERDUE_DAYS.' days') < $now;
    }

    private static function counted(int $count, string $singular, string $plural): string
    {
        if ($count === 0) {
            return 'No '.$plural;
        }

        return $count.' '.($count === 1 ? $singular : $plural);
    }
}
