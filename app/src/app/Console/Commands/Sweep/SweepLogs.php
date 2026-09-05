<?php

declare(strict_types=1);

namespace App\Console\Commands\Sweep;

use App\Console\Commands\Concerns\ReadsAsOf;
use App\Logging\LogStore;
use Illuminate\Console\Command;
use Throwable;

final class SweepLogs extends Command
{
    use ReadsAsOf;

    /** @var string */
    protected $signature = 'sweep:logs {--as-of= : Sweep as if it were this date, defaults to today}';

    /** @var string */
    protected $description = 'Prune stored log lines older than LOG_RETENTION_DAYS';

    /**
     * `LOG_RETENTION_DAYS=off` or a disabled store skips pruning silently —
     * neither is a failure.
     */
    public function handle(LogStore $logStore): int
    {
        $asOf = $this->asOf('the sweep can run as of');

        if ($asOf === null) {
            return self::FAILURE;
        }

        $retentionDays = config('log_store.retention_days');

        if ($logStore->connection === null || ! is_int($retentionDays)) {
            return self::SUCCESS;
        }

        try {
            $logStore->prune($asOf->modify("-{$retentionDays} days"));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("log retention prune failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
