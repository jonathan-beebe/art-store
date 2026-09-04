<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Auth\ActorType;
use App\Domain\Fulfillment\LaneFilter;
use App\Domain\Listings\ListingStatus;
use App\Domain\Seller\AttentionGroup;
use App\Domain\Seller\AttentionLinks;
use App\Domain\Seller\AttentionQueue;
use App\Domain\Seller\AttentionRow;
use App\Domain\Seller\AttentionRows;
use App\Domain\Seller\Initials;
use App\Domain\Seller\PayoutEstimate;
use App\Models\Conversation;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Seller;
use App\Support\RelativeTime;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The four queues the dashboard's focus row reads: parcels waiting to go
 * out, buyers waiting on a reply, the money settling toward the next
 * payout, and listings that cannot sell as they stand. Each queue is
 * counted whole and read down to {@see self::MAX_ROWS}, so a heading says
 * how big the pile is while the panel stays scannable.
 *
 * Every row is built here, where routes and models live;
 * {@see AttentionQueue} turns them into the groups the page renders.
 */
final readonly class NeedsAttention
{
    /** How many rows one focus panel shows. */
    private const int MAX_ROWS = 5;

    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return list<AttentionGroup>
     */
    public static function for(Seller $seller, PayoutEstimate $payout, DateTimeImmutable $now): array
    {
        return AttentionQueue::build(
            toShip: self::toShip($seller, $now),
            waiting: self::waiting($seller, $now),
            payout: self::payout($seller, $payout),
            listings: self::listings($seller, $now),
            payoutDate: $payout->payoutDate,
            links: new AttentionLinks(
                orders: route('seller.orders.index', ['lane' => LaneFilter::ToShip->value]),
                messages: route('seller.messages.index'),
                earnings: route('seller.earnings'),
                listings: route('seller.listings.index'),
            ),
        );
    }

    /**
     * Parcels nobody has started, oldest first — the one keeping a buyer
     * waiting longest leads.
     */
    private static function toShip(Seller $seller, DateTimeImmutable $now): AttentionRows
    {
        $query = Fulfillment::query()->whereBelongsTo($seller)->inLane(LaneFilter::ToShip);

        $parcels = (clone $query)
            ->with(['order', 'order.items' => fn (Relation $items) => $items->where('seller_id', $seller->id)])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::MAX_ROWS)
            ->get();

        $rows = $parcels->map(function (Fulfillment $parcel) use ($now): AttentionRow {
            $placedAt = $parcel->order->placed_at->toDateTimeImmutable();

            return new AttentionRow(
                initials: Initials::of($parcel->order->shipping_name),
                title: $parcel->order->shipping_name.' · '.$parcel->itemLabel(),
                supporting: $parcel->subtotal()->format(),
                meta: RelativeTime::long($placedAt, $now),
                href: route('seller.orders.show', ['fulfillment' => $parcel->id, 'lane' => LaneFilter::ToShip->value]),
                urgent: AttentionQueue::isOverdue($placedAt, $now),
            );
        })->all();

        return new AttentionRows(array_values($rows), $query->count());
    }

    /**
     * Buyer threads holding a message the seller has not read, newest
     * first.
     */
    private static function waiting(Seller $seller, DateTimeImmutable $now): AttentionRows
    {
        $query = Conversation::query()
            ->withParticipant($seller)
            ->whereNotNull('customer_id')
            ->unreadOnly($seller);

        $threads = (clone $query)
            ->with(['latestMessage', 'customer', 'seller'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(self::MAX_ROWS)
            ->get();

        $rows = [];

        foreach ($threads as $thread) {
            $latest = $thread->latestMessage;

            // A thread reads as unread because it holds a message nobody
            // opened, so the quote below is always there to read.
            if (! $latest instanceof Message) {
                continue;
            }

            $buyer = $thread->counterpartName(ActorType::Seller);

            $rows[] = new AttentionRow(
                initials: Initials::of($buyer),
                title: $buyer.' · '.$thread->title,
                supporting: $latest->body,
                meta: RelativeTime::short($latest->sent_at, $now),
                href: route('seller.messages.show', ['conversation' => $thread->id]),
            );
        }

        return new AttentionRows($rows, $query->count());
    }

    /**
     * The two halves of the money: what has released and is waiting on the
     * run, and what delivery has yet to free.
     */
    private static function payout(Seller $seller, PayoutEstimate $payout): AttentionRows
    {
        $held = HeldEscrow::for($seller);

        return AttentionRows::of([
            new AttentionRow(
                initials: '$',
                title: $payout->amount->format().' released and ready',
                supporting: self::plural($payout->releasedOrderCount, 'delivered order').' since the last payout',
                meta: $payout->payoutDate->format('M j'),
                href: route('seller.earnings'),
            ),
            new AttentionRow(
                initials: '$',
                title: $held->total->format().' still held',
                supporting: self::plural(count($held->orders), 'order').' waiting on delivery',
                meta: 'later',
                href: route('seller.orders.index', ['lane' => LaneFilter::InProgress->value]),
            ),
        ]);
    }

    /**
     * Drafts and sold-out pieces, most recently edited first — the two
     * states a listing sells nothing in. The publish panel on the
     * listing's own page says what a draft still needs.
     */
    private static function listings(Seller $seller, DateTimeImmutable $now): AttentionRows
    {
        $query = Listing::query()
            ->ofSeller($seller->id)
            ->whereIn('status', [ListingStatus::Draft, ListingStatus::Sold]);

        $listings = (clone $query)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::MAX_ROWS)
            ->get();

        $rows = $listings->map(fn (Listing $listing): AttentionRow => new AttentionRow(
            initials: Initials::of($listing->title),
            title: $listing->title,
            supporting: $listing->status === ListingStatus::Draft
                ? 'Draft · not on the storefront yet'
                : 'Sold out · restock it or archive it',
            meta: $listing->updated_at === null ? '' : 'Edited '.RelativeTime::long($listing->updated_at, $now),
            href: route('seller.listings.show', ['listing' => $listing->id]),
        ))->all();

        return new AttentionRows(array_values($rows), $query->count());
    }

    private static function plural(int $count, string $unit): string
    {
        return $count === 1 ? "1 {$unit}" : "{$count} {$unit}s";
    }
}
