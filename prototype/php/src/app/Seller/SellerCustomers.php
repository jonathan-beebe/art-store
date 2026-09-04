<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Messaging\ConversationStatus;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Domain\Seller\CustomerRow;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Fulfillment;
use App\Models\Seller;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Who has bought from a seller, and what each of them is worth. A customer
 * is derived from `fulfillments` rather than stored: a paid parcel that
 * still stands is what makes someone a seller's buyer. Browsing,
 * favoriting, and asking about a listing join a buyer's timeline once they
 * have bought.
 *
 * A fulfillment row exists from the moment an order is placed, so the paid
 * gate is what keeps an abandoned checkout out of the list and out of the
 * money — the same pair of conditions {@see ListingTable} counts a sale by.
 *
 * The aggregates are one grouped query; favorites, conversations, and the
 * names join by id in PHP.
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
        $totals = self::totalsByCustomer($seller, $only);

        if ($totals === []) {
            return [];
        }

        /** @var list<string> $customerIds */
        $customerIds = array_keys($totals);

        $accounts = self::accountIdentities($customerIds);
        $shipped = self::shippedIdentities($seller, self::idsMissingIdentity($accounts));
        $favorites = self::favoritesByCustomer($seller, $customerIds);
        $conversations = self::conversationsByCustomer($seller, $customerIds);

        $rows = [];

        foreach ($totals as $customerId => $total) {
            $account = $accounts[$customerId] ?? ['name' => null, 'email' => null];
            $order = $shipped[$customerId] ?? ['name' => '', 'email' => null];

            $rows[$customerId] = new CustomerRow(
                customerId: $customerId,
                name: $account['name'] ?? $order['name'],
                email: $account['email'] ?? $order['email'],
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
     * Each buyer's parcels folded into their figures, in one grouped query
     * over the seller's paid, still-standing parcels.
     *
     * @return array<string, array{orders: int, spentCents: int, firstOrderAt: DateTimeImmutable, lastOrderAt: DateTimeImmutable}>
     */
    private static function totalsByCustomer(Seller $seller, ?Customer $only): array
    {
        $rows = self::countedParcels($seller, $only)
            ->groupBy('fulfillments.customer_id')
            ->selectRaw('fulfillments.customer_id as customer_id, count(*) as orders, sum(fulfillments.subtotal_cents) as spent_cents, min(orders.placed_at) as first_order_at, max(orders.placed_at) as last_order_at')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[self::text($row->customer_id)] = [
                'orders' => self::number($row->orders),
                'spentCents' => self::number($row->spent_cents),
                'firstOrderAt' => self::moment($row->first_order_at),
                'lastOrderAt' => self::moment($row->last_order_at),
            ];
        }

        return $totals;
    }

    /**
     * The parcels a buyer's figures count: this seller's own, paid for, and
     * neither declined nor refunded.
     */
    private static function countedParcels(Seller $seller, ?Customer $only): QueryBuilder
    {
        $query = Fulfillment::query()
            ->join('orders', 'orders.id', '=', 'fulfillments.order_id')
            ->where('fulfillments.seller_id', $seller->id)
            ->whereIn('fulfillments.status', self::values(array_filter(
                FulfillmentStatus::cases(),
                fn (FulfillmentStatus $status): bool => $status->isLive(),
            )))
            ->whereIn('orders.status', self::values(array_filter(
                OrderStatus::cases(),
                fn (OrderStatus $status): bool => $status->hasBeenPaid(),
            )));

        if ($only instanceof Customer) {
            $query->where('fulfillments.customer_id', $only->id);
        }

        return $query->toBase();
    }

    /**
     * The name and address each buyer's own account holds. An anonymous
     * visitor's row holds neither.
     *
     * @param  list<string>  $customerIds
     * @return array<string, array{name: ?string, email: ?string}>
     */
    private static function accountIdentities(array $customerIds): array
    {
        $identities = [];

        foreach (Customer::query()->whereIn('id', $customerIds)->get(['id', 'name', 'email']) as $customer) {
            $identities[$customer->id] = ['name' => $customer->name, 'email' => $customer->email];
        }

        return $identities;
    }

    /**
     * @param  array<string, array{name: ?string, email: ?string}>  $accounts
     * @return list<string>
     */
    private static function idsMissingIdentity(array $accounts): array
    {
        $missing = array_filter(
            $accounts,
            fn (array $identity): bool => $identity['name'] === null || $identity['email'] === null,
        );

        return array_keys($missing);
    }

    /**
     * What the latest order carried for a buyer holding no account name or
     * address — a seller reads a name and an address because an order
     * carried them. Skipped when every buyer has both on their own account.
     *
     * @param  list<string>  $customerIds
     * @return array<string, array{name: string, email: ?string}>
     */
    private static function shippedIdentities(Seller $seller, array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $rows = self::countedParcels($seller, null)
            ->whereIn('fulfillments.customer_id', $customerIds)
            ->orderByDesc('orders.placed_at')
            ->get(['fulfillments.customer_id as customer_id', 'orders.shipping_name as shipping_name', 'orders.email as email']);

        $identities = [];

        foreach ($rows as $row) {
            // Newest first, so the first row read per buyer is their latest.
            $identities[self::text($row->customer_id)] ??= [
                'name' => self::text($row->shipping_name),
                'email' => is_string($row->email) ? $row->email : null,
            ];
        }

        return $identities;
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
        $rows = $query->toBase()
            ->groupBy('customer_id')
            ->selectRaw('customer_id, count(*) as tally')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[self::text($row->customer_id)] = self::number($row->tally);
        }

        return $counts;
    }

    /**
     * @param  array<int, FulfillmentStatus|OrderStatus>  $cases
     * @return list<string>
     */
    private static function values(array $cases): array
    {
        return array_values(array_map(
            fn (FulfillmentStatus|OrderStatus $status): string => $status->value,
            $cases,
        ));
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /** A timestamp the way the app's own datetime casts read one: UTC. */
    private static function moment(mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable(self::text($value), new DateTimeZone('UTC'));
    }
}
