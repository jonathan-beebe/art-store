<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\ChangeDirection;
use App\Domain\Analytics\RangeChange;
use App\Domain\Fulfillment\LaneFilter;
use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Seller\CustomerRow;
use App\Domain\Seller\FeedIcon;
use App\Domain\Seller\PayoutEstimate;
use App\Domain\Seller\Sparkline;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Seller;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The three numbers a seller's business reads as: who buys from them, what
 * was ordered in the range, and what those orders earned. Each comes back
 * as an {@see OverviewTile} carrying its figure, its change against the
 * range before it, its daily line, and the tool the tile opens.
 *
 * The buyers come from {@see SellerCustomers}, so the tile and the
 * customers table count the same people. Orders and earnings come from one
 * read of the seller's paid parcels across both ranges, folded by day in
 * PHP — a parcel declined or refunded later still counts as an order
 * placed, and earns nothing, which is how {@see EarningsPeriods} reads a
 * period.
 */
final readonly class SellerOverview
{
    private const int SPARKLINE_WIDTH = 120;

    private const int SPARKLINE_HEIGHT = 32;

    /**
     * @param  list<CustomerRow>  $customers
     * @param  array<string, int>  $ordersByDay  Y-m-d => parcels placed
     * @param  array<string, int>  $netByDay  Y-m-d => net cents earned
     */
    private function __construct(
        private AnalyticsRange $range,
        private array $customers,
        private array $ordersByDay,
        private array $netByDay,
        private int $toShip,
        private PayoutEstimate $payout,
    ) {}

    public static function for(Seller $seller, AnalyticsRange $range, PayoutEstimate $payout): self
    {
        $placed = self::parcelsPlacedBetween($seller, $range->previous()->start, $range->end);

        return new self(
            range: $range,
            customers: SellerCustomers::forSeller($seller),
            ordersByDay: $placed['orders'],
            netByDay: $placed['net'],
            toShip: Fulfillment::query()->whereBelongsTo($seller)->inLane(LaneFilter::ToShip)->count(),
            payout: $payout,
        );
    }

    /**
     * @return list<OverviewTile>
     */
    public function tiles(): array
    {
        return [$this->customersTile(), $this->ordersTile(), $this->earningsTile()];
    }

    /**
     * Buyers all-time, with the ones whose first order landed inside the
     * range called out — a seller reads their customer base as a total and
     * their growth as an arrival count.
     */
    private function customersTile(): OverviewTile
    {
        // The figure is the customers table's own rule; the day fold below
        // it is only the shape of the line.
        $newInRange = count(array_filter(
            $this->customers,
            fn (CustomerRow $customer): bool => $customer->isNewSince($this->range->start),
        ));

        return new OverviewTile(
            icon: FeedIcon::Users,
            label: 'Customers',
            value: number_format(count($this->customers)),
            changeText: '+'.number_format($newInRange).' new',
            changeDirection: $newInRange > 0 ? ChangeDirection::Up : ChangeDirection::Flat,
            sparkline: $this->sparkline($this->arrivalsByDay()),
            footerLabel: 'View customers',
            footerNote: number_format($newInRange).' new in '.$this->range->days.' days',
            href: route('seller.customers.index', ['range' => $this->range->days]),
        );
    }

    private function ordersTile(): OverviewTile
    {
        $current = array_sum($this->over($this->ordersByDay, $this->range));
        $change = RangeChange::between($current, array_sum($this->over($this->ordersByDay, $this->range->previous())));

        return new OverviewTile(
            icon: FeedIcon::Bag,
            label: 'Orders',
            value: number_format($current),
            changeText: $change->text,
            changeDirection: $change->direction,
            sparkline: $this->sparkline($this->ordersByDay),
            footerLabel: 'Manage orders',
            footerNote: number_format($this->toShip).' to ship',
            href: route('seller.orders.index', ['lane' => LaneFilter::ToShip->value]),
        );
    }

    private function earningsTile(): OverviewTile
    {
        $current = array_sum($this->over($this->netByDay, $this->range));
        $change = RangeChange::between($current, array_sum($this->over($this->netByDay, $this->range->previous())));

        return new OverviewTile(
            icon: FeedIcon::Cash,
            label: 'Earnings',
            value: Money::fromCents($current)->format(),
            changeText: $change->text,
            changeDirection: $change->direction,
            sparkline: $this->sparkline($this->netByDay),
            footerLabel: 'See earnings',
            footerNote: 'Next payout '.$this->payout->payoutDate->format('M j'),
            href: route('seller.earnings'),
        );
    }

    /**
     * How many buyers placed their first order on each day.
     *
     * @return array<string, int>
     */
    private function arrivalsByDay(): array
    {
        $arrivals = [];

        foreach ($this->customers as $customer) {
            $day = $customer->firstOrderAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
            $arrivals[$day] = ($arrivals[$day] ?? 0) + 1;
        }

        return $arrivals;
    }

    /**
     * @param  array<string, int>  $byDay
     */
    private function sparkline(array $byDay): Sparkline
    {
        return Sparkline::of($this->over($byDay, $this->range), self::SPARKLINE_WIDTH, self::SPARKLINE_HEIGHT);
    }

    /**
     * One window's days read off a day-keyed fold, oldest first, a day
     * nothing happened on reading zero.
     *
     * @param  array<string, int>  $byDay
     * @return list<int>
     */
    private function over(array $byDay, AnalyticsRange $window): array
    {
        return array_map(fn (string $day): int => $byDay[$day] ?? 0, $window->dayLabels());
    }

    /**
     * The seller's paid parcels placed between the two instants, folded by
     * the UTC day the order was placed on: how many parcels, and what the
     * live ones among them earned. One query, no models, no date function
     * in the SQL.
     *
     * @return array{orders: array<string, int>, net: array<string, int>}
     */
    private static function parcelsPlacedBetween(Seller $seller, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = Fulfillment::query()
            ->join('orders', 'orders.id', '=', 'fulfillments.order_id')
            ->where('fulfillments.seller_id', $seller->id)
            ->whereIn('orders.status', Order::paidStatuses())
            ->where('orders.placed_at', '>=', $from)
            ->where('orders.placed_at', '<=', $to)
            ->toBase()
            ->get(['orders.placed_at as placed_at', 'fulfillments.status as status', 'fulfillments.net_cents as net_cents']);

        $orders = [];
        $net = [];

        foreach ($rows as $row) {
            $day = self::day($row->placed_at);
            $orders[$day] = ($orders[$day] ?? 0) + 1;
            $net[$day] ??= 0;

            if (FulfillmentStatus::from(self::text($row->status))->isLive()) {
                $net[$day] += self::number($row->net_cents);
            }
        }

        return ['orders' => $orders, 'net' => $net];
    }

    private static function day(mixed $value): string
    {
        return (new DateTimeImmutable(self::text($value), new DateTimeZone('UTC')))->format('Y-m-d');
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
