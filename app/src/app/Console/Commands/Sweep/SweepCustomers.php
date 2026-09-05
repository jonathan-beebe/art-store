<?php

declare(strict_types=1);

namespace App\Console\Commands\Sweep;

use App\Actions\Customers\SweepAnonymousCustomers;
use App\Console\Commands\Concerns\ReadsAsOf;
use Illuminate\Console\Command;
use Throwable;

final class SweepCustomers extends Command
{
    use ReadsAsOf;

    /** @var string */
    protected $signature = 'sweep:customers {--as-of= : Sweep as if it were this date, defaults to today}';

    /** @var string */
    protected $description = 'Delete an anonymous customer that owns nothing, once it is older than ANONYMOUS_CUSTOMER_RETENTION_DAYS';

    /**
     * `ANONYMOUS_CUSTOMER_RETENTION_DAYS=off` skips the sweep silently and
     * still succeeds.
     */
    public function handle(SweepAnonymousCustomers $sweepAnonymousCustomers): int
    {
        $asOf = $this->asOf('the sweep can run as of');

        if ($asOf === null) {
            return self::FAILURE;
        }

        $retentionDays = config('customers.anonymous_retention_days');

        if (! is_int($retentionDays)) {
            return self::SUCCESS;
        }

        $this->info("Deleting anonymous customers idle for {$retentionDays} days");

        try {
            $deleted = $sweepAnonymousCustomers($asOf, $retentionDays);
        } catch (Throwable $e) {
            $this->error("customer sweep failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info($this->summarise($deleted));

        return self::SUCCESS;
    }

    private function summarise(int $deleted): string
    {
        return $deleted === 0
            ? 'No anonymous customer has been idle that long.'
            : "{$deleted} anonymous customer(s) deleted.";
    }
}
