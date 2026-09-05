<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Messaging\ConversationStatus;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Domain\Seller\CustomerRow;
use App\Domain\Seller\CustomerSegment;
use App\Domain\Seller\CustomerSortColumn;
use App\Domain\Seller\CustomerTallyFacts;
use App\Domain\Seller\SortDirection;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Seller;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Who has bought from a seller, and what each of them is worth. Every read
 * derives the list from `fulfillments`; no table holds a seller's customer.
 * A paid parcel that still stands is what makes someone a buyer, and
 * browsing, favoriting, and asking about a listing join their timeline once
 * they have bought.
 *
 * A fulfillment row exists from the moment an order is placed, so
 * {@see Fulfillment::counted()} is what keeps an abandoned checkout out of
 * the list and out of the money.
 *
 * The aggregates are one grouped query; favorites, conversations, and the
 * names join by id in PHP.
 *
 * {@see forSeller()} reads every buyer unfiltered, unsorted, and
 * unpaged, for the callers that need a row per buyer.
 * {@see tallyFor()} is what the tiles above the customers table read
 * instead: the same buyers folded to five figures in one query, so
 * counting them costs one round trip for all five figures.
 * {@see pageForSeller()} is the table's own source: one segment, sorted
 * and paged, entirely in the query.
 */
final class SellerCustomers
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * Every buyer of this seller, in no particular order —
     * {@see \App\Domain\Seller\RowSort} orders a list of them.
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
     * How many of this seller's buyers `$segment` keeps — a pager's page
     * count is built from this, over the same `HAVING` a page of rows
     * reads.
     */
    public static function countForSegment(Seller $seller, CustomerSegment $segment, DateTimeImmutable $newSince): int
    {
        return DB::query()
            ->fromSub(self::segmentedQuery($seller, $segment, $newSince)->select('fulfillments.customer_id'), 'buyers')
            ->count();
    }

    /**
     * Every buyer folded to five figures in one query: how many there
     * are, how many are new since `$newSince`, how many are repeat
     * buyers, and what they have ordered and spent — the tiles above the
     * customers table read this, so they count every buyer whatever the
     * table's segment or page shows.
     */
    public static function tallyFor(Seller $seller, DateTimeImmutable $newSince): CustomerTallyFacts
    {
        $inner = self::segmentedQuery($seller, CustomerSegment::All, $newSince);

        $row = (array) (DB::query()
            ->fromSub($inner, 'buyers')
            ->selectRaw(
                'count(*) as customers, sum(orders) as orders, sum(spent_cents) as spent_cents, '
                .'sum(case when orders >= ? then 1 else 0 end) as repeat_buyers, '
                .'sum(case when first_order_at >= ? then 1 else 0 end) as new_this_period',
                [CustomerRow::REPEAT_ORDERS, $newSince->format('Y-m-d H:i:s')],
            )
            ->first() ?? []);

        return new CustomerTallyFacts(
            customers: self::number($row['customers'] ?? null),
            newThisPeriod: self::number($row['new_this_period'] ?? null),
            repeatBuyers: self::number($row['repeat_buyers'] ?? null),
            orders: self::number($row['orders'] ?? null),
            spentCents: self::number($row['spent_cents'] ?? null),
        );
    }

    /**
     * One page of this seller's buyers narrowed to `$segment`, sorted by
     * `$column`/`$direction`, `$limit` rows from `$offset` — a page
     * never costs reading the rows either side of it. The aggregate
     * wraps in `fromSub()` before it sorts: the inner query joins
     * `fulfillments`, `orders`, and `customers`, so a bare column name
     * in `ORDER BY` (`orders`, `name`) could resolve against any of
     * those joined tables. Wrapping narrows resolution to the
     * aggregate's own column list.
     *
     * @return list<CustomerRow>
     */
    public static function pageForSeller(Seller $seller, CustomerSegment $segment, DateTimeImmutable $newSince, CustomerSortColumn $column, SortDirection $direction, int $limit, int $offset): array
    {
        $inner = self::segmentedQuery($seller, $segment, $newSince);
        self::addIdentityAndActivity($inner, $seller);

        $query = DB::query()->fromSub($inner, 'buyers');
        self::orderBy($query, $column, $direction);

        $rows = $query->limit($limit)->offset($offset)->get();

        return array_values(array_map(self::toRowFromQuery(...), $rows->all()));
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
     * The parcels a buyer's figures count — {@see Fulfillment::counted()} —
     * joined to `orders` for the columns below that read off it.
     */
    private static function countedParcels(Seller $seller, ?Customer $only): QueryBuilder
    {
        $query = Fulfillment::query()
            ->join('orders', 'orders.id', '=', 'fulfillments.order_id')
            ->where('fulfillments.seller_id', $seller->id)
            ->counted();

        if ($only instanceof Customer) {
            $query->where('fulfillments.customer_id', $only->id);
        }

        return $query->toBase();
    }

    /**
     * Every buyer's parcels folded into their figures, narrowed to
     * `$segment` in the `HAVING` clause — the aggregate a page of rows
     * and a page count both read.
     */
    private static function segmentedQuery(Seller $seller, CustomerSegment $segment, DateTimeImmutable $newSince): QueryBuilder
    {
        $query = self::countedParcels($seller, null)
            ->groupBy('fulfillments.customer_id')
            ->selectRaw('fulfillments.customer_id as customer_id, count(*) as orders, sum(fulfillments.subtotal_cents) as spent_cents, min(orders.placed_at) as first_order_at, max(orders.placed_at) as last_order_at');

        match ($segment) {
            CustomerSegment::All => null,
            CustomerSegment::Repeat => $query->havingRaw('count(*) >= ?', [CustomerRow::REPEAT_ORDERS]),
            CustomerSegment::New => $query->havingRaw('min(orders.placed_at) >= ?', [$newSince->format('Y-m-d H:i:s')]),
        };

        return $query;
    }

    /**
     * The name, email, favorites, and conversations a page of rows needs
     * beyond the aggregate — added to `$query`'s select list, each
     * correlated on the grouped `fulfillments.customer_id`, so the page
     * still costs one query. Name and email each carry the account's own
     * column and, alongside it, the latest counted parcel's shipped
     * name/email — {@see self::toRowFromQuery()} picks whichever the
     * account left null, the same fallback {@see self::shippedIdentities()}
     * applies in PHP for {@see self::rows()}'s unpaged callers.
     */
    private static function addIdentityAndActivity(QueryBuilder $query, Seller $seller): void
    {
        $query
            ->leftJoin('customers', 'customers.id', '=', 'fulfillments.customer_id')
            ->addSelect(['customers.name as account_name', 'customers.email as account_email'])
            ->selectSub(
                fn (QueryBuilder $shipped): QueryBuilder => self::latestShippedOrder($shipped, $seller)->select('shipped_o.shipping_name'),
                'shipped_name',
            )
            ->selectSub(
                fn (QueryBuilder $shipped): QueryBuilder => self::latestShippedOrder($shipped, $seller)->select('shipped_o.email'),
                'shipped_email',
            )
            ->selectSub(
                fn (QueryBuilder $favorites): QueryBuilder => $favorites->from('favorites')
                    ->join('listings', 'listings.id', '=', 'favorites.listing_id')
                    ->whereColumn('favorites.customer_id', 'fulfillments.customer_id')
                    ->where('listings.seller_id', $seller->id)
                    ->selectRaw('count(*)'),
                'favorites',
            )
            ->selectSub(
                fn (QueryBuilder $conversations): QueryBuilder => $conversations->from('conversations')
                    ->whereColumn('conversations.customer_id', 'fulfillments.customer_id')
                    ->where('conversations.seller_id', $seller->id)
                    ->selectRaw('count(*)'),
                'conversations',
            );
    }

    /**
     * The order behind a buyer's most recent counted parcel with this
     * seller, correlated on the grouped `fulfillments.customer_id` — the
     * same live-and-paid pair {@see countedParcels()} filters by, so a
     * shipped name or email never comes from a parcel that settled back.
     */
    private static function latestShippedOrder(QueryBuilder $query, Seller $seller): QueryBuilder
    {
        $liveStatuses = array_map(fn (FulfillmentStatus $status): string => $status->value, Fulfillment::liveStatuses());
        $paidStatuses = array_map(fn (OrderStatus $status): string => $status->value, Order::paidStatuses());

        return $query
            ->from('fulfillments as shipped_f')
            ->join('orders as shipped_o', 'shipped_o.id', '=', 'shipped_f.order_id')
            ->whereColumn('shipped_f.customer_id', 'fulfillments.customer_id')
            ->where('shipped_f.seller_id', $seller->id)
            ->whereIn('shipped_f.status', $liveStatuses)
            ->whereIn('shipped_o.status', $paidStatuses)
            ->orderByDesc('shipped_o.placed_at')
            ->orderByDesc('shipped_f.id')
            ->limit(1);
    }

    /** The column a page of rows orders by, the id ascending underneath it whichever way that column runs. */
    private static function orderBy(QueryBuilder $query, CustomerSortColumn $column, SortDirection $direction): void
    {
        $expression = match ($column) {
            CustomerSortColumn::Name => 'lower(coalesce(account_name, shipped_name))',
            CustomerSortColumn::Orders => 'orders',
            CustomerSortColumn::Spent => 'spent_cents',
            CustomerSortColumn::Favorites => 'favorites',
            CustomerSortColumn::LastOrder => 'last_order_at',
            CustomerSortColumn::Conversations => 'conversations',
            CustomerSortColumn::Since => 'first_order_at',
        };

        $query
            ->orderByRaw("{$expression} {$direction->value}")
            ->orderBy('customer_id', 'asc');
    }

    /**
     * The account's own name/email wins over the shipped fallback,
     * {@see self::addIdentityAndActivity()}'s pair of columns for each —
     * the same precedence {@see self::rows()} applies in PHP.
     */
    private static function toRowFromQuery(object $row): CustomerRow
    {
        $data = (array) $row;

        $accountName = $data['account_name'] ?? null;
        $accountEmail = $data['account_email'] ?? null;

        return new CustomerRow(
            customerId: self::text($data['customer_id'] ?? null),
            name: is_string($accountName) ? $accountName : self::text($data['shipped_name'] ?? null),
            email: is_string($accountEmail) ? $accountEmail : (is_string($data['shipped_email'] ?? null) ? $data['shipped_email'] : null),
            orders: self::number($data['orders'] ?? null),
            spentCents: self::number($data['spent_cents'] ?? null),
            favorites: self::number($data['favorites'] ?? null),
            conversations: self::number($data['conversations'] ?? null),
            firstOrderAt: self::moment($data['first_order_at'] ?? null),
            lastOrderAt: self::moment($data['last_order_at'] ?? null),
        );
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

        $latest = self::countedParcels($seller, null)
            ->whereIn('fulfillments.customer_id', $customerIds)
            ->selectRaw('fulfillments.customer_id as customer_id, max(orders.placed_at) as placed_at')
            ->groupBy('fulfillments.customer_id');

        $rows = self::countedParcels($seller, null)
            ->whereIn('fulfillments.customer_id', $customerIds)
            ->joinSub($latest, 'latest_order', function (JoinClause $join): void {
                $join->on('fulfillments.customer_id', '=', 'latest_order.customer_id')
                    ->on('orders.placed_at', '=', 'latest_order.placed_at');
            })
            ->get(['fulfillments.customer_id as customer_id', 'orders.shipping_name as shipping_name', 'orders.email as email']);

        $identities = [];

        foreach ($rows as $row) {
            // A tie on placed_at keeps the first row read for a buyer.
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
