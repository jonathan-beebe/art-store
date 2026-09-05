<?php

declare(strict_types=1);

namespace App\Console\Commands\Sweep;

use App\Analytics\Analytics;
use App\Console\Commands\Concerns\ReadsAsOf;
use Illuminate\Console\Command;
use Throwable;

final class SweepAnalytics extends Command
{
    use ReadsAsOf;

    /** @var string */
    protected $signature = 'sweep:analytics {--as-of= : Sweep as if it were this date, defaults to today}';

    /** @var string */
    protected $description = 'Prune analytics events and visits older than ANALYTICS_RETENTION_DAYS';

    /**
     * `ANALYTICS_RETENTION_DAYS=off` skips pruning silently and still
     * succeeds. `Analytics::prune()`'s count spans both tables it deletes
     * from — events and visits — so the printed line does too.
     */
    public function handle(Analytics $analytics): int
    {
        $asOf = $this->asOf('the sweep can run as of');

        if ($asOf === null) {
            return self::FAILURE;
        }

        $retentionDays = config('analytics.retention_days');

        if (! is_int($retentionDays)) {
            return self::SUCCESS;
        }

        try {
            $deleted = $analytics->prune($asOf->modify("-{$retentionDays} days"));
            $this->info("{$deleted} analytics row(s) pruned.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("analytics retention prune failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
