<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Messaging\ConversationStatus;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Seller\CustomerRow;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Fulfillment;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Who has bought from a seller, and what each of them is worth. A customer
 * is derived from `fulfillments` rather than stored: a live parcel is what
 * makes someone a seller's buyer, and browsing, favoriting, or asking about
 * a listing never does. Favorites and conversations join by id in PHP, so
 * they colour a buyer the seller already has.
 */
final class SellerCustomers
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * Every buyer of this seller, in no particular order —
     * {@see \App\Domain\Seller\CustomerTableSort} orders a list of them.
     *
     * @return list<CustomerRow>
     */
    public static function forSeller(Seller $seller): array
    {
        return array_values(self::rows($seller, null));
    }

    /**
     * One buyer's row, or null for a customer who has never bought from
     * this seller — the customer page's 404 and the thread rail's "no
     * numbers to show" both read this.
     */
    public static function forCustomer(Seller $seller, Customer $customer): ?CustomerRow
    {
        return self::rows($seller, $customer)[$customer->id] ?? null;
    }

    /**
     * The seller's buyer threads: how many are open, and how many of those
     * hold a message they have not read.
     */
    public static function conversationCounts(Seller $seller): ConversationCounts
    {
        $open = Conversation::query()
            ->withParticipant($seller)
            ->whereNotNull('customer_id')
            ->withStatus(ConversationStatus::Open);

        return new ConversationCounts(
            open: (clone $open)->count(),
            unread: $open->unreadOnly($seller)->count(),
        );
    }

    /**
     * @return array<string, CustomerRow> customer id => row
     */
    private static function rows(Seller $seller, ?Customer $only): array
    {
        $totals = self::totalsByCustomer(self::liveFulfillments($seller, $only));

        if ($totals === []) {
            return [];
        }

        /** @var list<string> $customerIds */
        $customerIds = array_keys($totals);

        $accounts = Customer::query()->whereIn('id', $customerIds)->get()->keyBy('id');
        $favorites = self::favoritesByCustomer($seller, $customerIds);
        $conversations = self::conversationsByCustomer($seller, $customerIds);

        $rows = [];

        foreach ($totals as $customerId => $total) {
            // A fulfillment names a customer row, so there is always one to
            // read; a name and an address are what an anonymous one lacks.
            $account = $accounts->get($customerId);
            $given = $account instanceof Customer ? $account->name : null;
            $address = $account instanceof Customer ? $account->email : null;

            $rows[$customerId] = new CustomerRow(
                customerId: $customerId,
                name: $given ?? $total['shippingName'],
                email: $address ?? $total['email'],
                orders: $total['orders'],
                spentCents: $total['spentCents'],
                favorites: $favorites[$customerId] ?? 0,
                conversations: $conversations[$customerId] ?? 0,
                firstOrderAt: $total['firstOrderAt'],
                lastOrderAt: $total['lastOrderAt'],
            );
        }

        return $rows;
    }

    /**
     * The seller's parcels that still stand, oldest order first — a
     * declined or refunded one sent the money back, and the person behind
     * it is no longer a buyer on its account.
     *
     * @return Collection<int, Fulfillment>
     */
    private static function liveFulfillments(Seller $seller, ?Customer $only): Collection
    {
        $live = array_values(array_filter(
            FulfillmentStatus::cases(),
            fn (FulfillmentStatus $status): bool => $status->isLive(),
        ));

        $query = Fulfillment::query()
            ->where('seller_id', $seller->id)
            ->whereIn('status', $live)
            ->with('order:id,placed_at,shipping_name,email');

        if ($only instanceof Customer) {
            $query->where('customer_id', $only->id);
        }

        return $query->get()->sortBy(fn (Fulfillment $fulfillment): int => self::placedAt($fulfillment)->getTimestamp())->values();
    }

    /**
     * Each buyer's parcels folded into one set of totals, read oldest
     * first. A seller sees a name and an email because an order carries
     * them, so the latest order — the last one folded in — names a buyer
     * holding no account of their own.
     *
     * @param  Collection<int, Fulfillment>  $fulfillments
     * @return array<string, array{orders: int, spentCents: int, firstOrderAt: DateTimeImmutable, lastOrderAt: DateTimeImmutable, shippingName: string, email: ?string}>
     */
    private static function totalsByCustomer(Collection $fulfillments): array
    {
        $totals = [];

        foreach ($fulfillments as $fulfillment) {
            $placedAt = self::placedAt($fulfillment);
            $customerId = $fulfillment->customer_id;
            $carried = $totals[$customerId] ?? null;

            $totals[$customerId] = [
                'orders' => ($carried['orders'] ?? 0) + 1,
                'spentCents' => ($carried['spentCents'] ?? 0) + $fulfillment->subtotal_cents,
                'firstOrderAt' => $carried['firstOrderAt'] ?? $placedAt,
                'lastOrderAt' => $placedAt,
                'shippingName' => $fulfillment->order->shipping_name,
                'email' => $fulfillment->order->email,
            ];
        }

        return $totals;
    }

    private static function placedAt(Fulfillment $fulfillment): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface(
            $fulfillment->order->placed_at ?? throw new LogicException('A placed order always carries placed_at.'),
        );
    }

    /**
     * How many of this seller's listings each buyer holds as a favorite.
     *
     * @param  list<string>  $customerIds
     * @return array<string, int>
     */
    private static function favoritesByCustomer(Seller $seller, array $customerIds): array
    {
        return self::talliedByCustomer(
            Favorite::query()
                ->whereIn('customer_id', $customerIds)
                ->whereHas('listing', fn (Builder $listings): Builder => $listings->where('seller_id', $seller->id)),
        );
    }

    /**
     * How many threads each buyer holds with this seller, whatever their
     * status.
     *
     * @param  list<string>  $customerIds
     * @return array<string, int>
     */
    private static function conversationsByCustomer(Seller $seller, array $customerIds): array
    {
        return self::talliedByCustomer(
            Conversation::query()
                ->withParticipant($seller)
                ->whereIn('customer_id', $customerIds),
        );
    }

    /**
     * @param  Builder<Conversation>|Builder<Favorite>  $query
     * @return array<string, int>
     */
    private static function talliedByCustomer(Builder $query): array
    {
        /** @var array<string, mixed> $tallies */
        $tallies = $query
            ->groupBy('customer_id')
            ->selectRaw('customer_id, count(*) as tally')
            ->pluck('tally', 'customer_id')
            ->all();

        $counts = [];

        foreach ($tallies as $customerId => $tally) {
            $counts[(string) $customerId] = is_numeric($tally) ? (int) $tally : 0;
        }

        return $counts;
    }
}
