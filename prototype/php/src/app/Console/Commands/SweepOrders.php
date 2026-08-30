<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Orders\SweepStaleOrders;
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
    protected $description = 'Cancel the orders a guest never verified, once they are older than STALE_ORDER_HOURS, and prune the log store past its retention window';

    public function handle(SweepStaleOrders $sweepStaleOrders, LogStore $logStore): int
    {
        $rawAsOf = $this->option('as-of');
        $asOfInput = is_string($rawAsOf) && $rawAsOf !== '' ? $rawAsOf : null;

        try {
            $asOf = $asOfInput === null ? now()->toDateTimeImmutable() : new DateTimeImmutable($asOfInput);
        } catch (DateMalformedStringException) {
            $this->error("\"{$asOfInput}\" is not a date the sweep can run as of.");

            return self::FAILURE;
        }

        $staleSweepSucceeded = $this->sweepStaleOrders($sweepStaleOrders, $asOf);
        $pruneSucceeded = $this->pruneLogLines($logStore, $asOf);

        return $staleSweepSucceeded && $pruneSucceeded ? self::SUCCESS : self::FAILURE;
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
     * @param  list<Order>  $cancelled
     */
    private function summarise(array $cancelled): string
    {
        return $cancelled === []
            ? 'No order has been waiting that long.'
            : count($cancelled).' order(s) cancelled.';
    }
}
