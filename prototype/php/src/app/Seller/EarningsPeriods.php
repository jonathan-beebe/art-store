<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Analytics\RangeChange;
use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Escrow\PayoutPeriod;
use App\Domain\Money\Money;
use App\Domain\Seller\PeriodFigures;
use App\Domain\Seller\PeriodSettlement;
use App\Domain\Seller\RefundFact;
use App\Domain\Seller\SaleFact;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use RuntimeException;

/**
 * The last eight payout periods' business, the current one in progress
 * last, matched against whichever of them have a `payouts` row. The window
 * is the same eight periods the earnings page charts and lists, so one read
 * of the ledger and the fulfillments table backs both.
 */
final readonly class EarningsPeriods
{
    private const int WINDOW = 8;

    /**
     * @param  list<PeriodFigures>  $periods  oldest first, the last entry is the period in progress
     * @param  array<string, Payout>  $payoutsByPeriodStart  period_start ("Y-m-d") => Payout
     */
    private function __construct(
        public array $periods,
        private array $payoutsByPeriodStart,
    ) {}

    public static function for(Seller $seller, DateTimeImmutable $now): self
    {
        $periods = self::window(PayoutPeriod::containing($now));
        $windowStart = $periods[0]->start;

        $sales = array_values($seller->fulfillments()
            ->whereHas('order', fn (Builder $orders): Builder => $orders
                ->where('placed_at', '>=', $windowStart)
                ->whereIn('status', Order::paidStatuses()))
            ->with('order')
            ->get()
            ->map(self::toSaleFact(...))
            ->all());

        $refunds = array_values($seller->ledgerEntries()
            ->ofType(LedgerEntryType::Refunded)
            ->where('occurred_at', '>=', $windowStart)
            ->get()
            ->map(self::toRefundFact(...))
            ->all());

        // A seller's payouts are few: reading them all and keying them here
        // by the formatted date sidesteps whatever format the query
        // grammar would bind a `whereIn` on the plain-date `period_start`
        // column as.
        $payouts = $seller->payouts()
            ->get()
            ->keyBy(fn (Payout $payout): string => $payout->period_start->format('Y-m-d'));

        /** @var array<string, Payout> $payoutsByPeriodStart */
        $payoutsByPeriodStart = $payouts->all();

        return new self(PeriodFigures::bucket($periods, $sales, $refunds), $payoutsByPeriodStart);
    }

    public function current(): PeriodFigures
    {
        return $this->periods === []
            ? throw new LogicException('An earnings window always carries at least the period in progress.')
            : $this->periods[array_key_last($this->periods)];
    }

    /**
     * The completed periods before the current one, newest first.
     *
     * @return list<PeriodFigures>
     */
    public function past(): array
    {
        return array_reverse(array_slice($this->periods, 0, -1));
    }

    /**
     * The tallest net in the window, or one cent so a chart over an empty
     * window never divides by zero.
     */
    public function tallestNet(): Money
    {
        $cents = array_map(fn (PeriodFigures $figures): int => abs($figures->net()->cents), $this->periods);

        return Money::fromCents(max([1, ...$cents]));
    }

    /**
     * How this period's sales compare with the period right before it.
     */
    public function currentSalesChange(): RangeChange
    {
        return $this->current()->salesChange($this->past()[0]);
    }

    public function settlementOf(PeriodFigures $figures): PeriodSettlement
    {
        $payout = $this->payoutsByPeriodStart[$figures->period->start->format('Y-m-d')] ?? null;

        return PeriodSettlement::of(
            isCurrent: $figures === $this->current(),
            hasPayoutRow: $payout !== null,
            paidAt: $payout?->paid_at->toDateTimeImmutable(),
        );
    }

    /**
     * @return list<PayoutPeriod> oldest first, ending with $current
     */
    private static function window(PayoutPeriod $current): array
    {
        $periods = [$current];

        for ($i = 1; $i < self::WINDOW; $i++) {
            array_unshift($periods, $periods[0]->previous());
        }

        return $periods;
    }

    private static function toSaleFact(Fulfillment $fulfillment): SaleFact
    {
        /** @var Order $order */
        $order = $fulfillment->order;
        $placedAt = $order->placed_at ?? throw new RuntimeException('An order behind a fulfillment always carries a placed_at.');

        return new SaleFact(
            $placedAt->toDateTimeImmutable(),
            $fulfillment->subtotal(),
            $fulfillment->fee(),
        );
    }

    private static function toRefundFact(LedgerEntry $entry): RefundFact
    {
        return new RefundFact(
            $entry->occurred_at->toDateTimeImmutable(),
            Money::fromCents(-$entry->amount_cents),
        );
    }
}
