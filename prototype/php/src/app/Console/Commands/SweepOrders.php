<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Orders\SweepStaleOrders;
use App\Analytics\Analytics;
use App\Logging\LogStore;
use App\Models\Order;
use DateMalformedStringException;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Throwable;

final class SweepOrders extends Command
{
    /** @var string */
    protected $signature = 'orders:sweep {--as-of= : Sweep as if it were this date, defaults to today}';

    /** @var string */
    protected $description = 'Cancel the orders a guest never verified, once they are older than STALE_ORDER_HOURS, and prune the log and analytics stores past their retention windows';

    public function handle(SweepStaleOrders $sweepStaleOrders, LogStore $logStore, Analytics $analytics): int
    {
        $rawAsOf = $this->option('as-of');
        $asOfInput = is_string($rawAsOf) && $rawAsOf !== '' ? $rawAsOf : null;

        try {
            $asOf = $asOfInput === null ? now()->toDateTimeImmutable() : new DateTimeImmutable($asOfInput);
        } catch (DateMalformedStringException) {
            $this->error("\"{$asOfInput}\" is not a date the sweep can run as of.");

            return self::FAILURE;
        }

        // Each step runs whatever the others did, so a failure in one
        // never hides the other two's completed work.
        $staleSweepSucceeded = $this->sweepStaleOrders($sweepStaleOrders, $asOf);
        $logPruneSucceeded = $this->pruneLogLines($logStore, $asOf);
        $analyticsPruneSucceeded = $this->pruneAnalyticsEvents($analytics, $asOf);

        return $staleSweepSucceeded && $logPruneSucceeded && $analyticsPruneSucceeded ? self::SUCCESS : self::FAILURE;
    }

    private function sweepStaleOrders(SweepStaleOrders $sweepStaleOrders, DateTimeImmutable $asOf): bool
    {
        $staleHours = (int) config('orders.stale_hours');

        $this->info("Cancelling orders left unverified for {$staleHours} hours");

        try {
            $cancelled = $sweepStaleOrders($asOf, $staleHours);
        } catch (Throwable $e) {
            $this->error("order sweep failed: {$e->getMessage()}");

            return false;
        }

        foreach ($cancelled as $order) {
            $this->line("{$order->id} {$order->total()->format()}");
        }

        $this->info($this->summarise($cancelled));

        return true;
    }

    /**
     * `LOG_RETENTION_DAYS=off` or a disabled store skips pruning silently —
     * neither is a failure. A prune failure sets the command's exit code
     * but leaves the stale-order sweep's completed work standing; it never
     * escapes as an uncaught exception.
     */
    private function pruneLogLines(LogStore $logStore, DateTimeImmutable $asOf): bool
    {
        $retentionDays = config('log_store.retention_days');

        if ($logStore->connection === null || ! is_int($retentionDays)) {
            return true;
        }

        try {
            $logStore->prune($asOf->modify("-{$retentionDays} days"));

            return true;
        } catch (Throwable $e) {
            $this->error("log retention prune failed: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * `ANALYTICS_RETENTION_DAYS=off` skips pruning silently — not a
     * failure. A prune failure sets the command's exit code but leaves the
     * other two steps' completed work standing; it never escapes as an
     * uncaught exception.
     */
    private function pruneAnalyticsEvents(Analytics $analytics, DateTimeImmutable $asOf): bool
    {
        $retentionDays = config('analytics.retention_days');

        if (! is_int($retentionDays)) {
            return true;
        }

        try {
            $analytics->prune($asOf->modify("-{$retentionDays} days"));

            return true;
        } catch (Throwable $e) {
            $this->error("analytics retention prune failed: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * @param  list<Order>  $cancelled
     */
    private function summarise(array $cancelled): string
    {
        return $cancelled === []
            ? 'No order has been waiting that long.'
            : count($cancelled).' order(s) cancelled.';
    }
}
