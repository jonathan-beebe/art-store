<?php

namespace App\Console\Commands;

use App\Actions\Escrow\RunWeeklyPayout;
use App\Domain\Escrow\PayoutPeriod;
use App\Models\Payout;
use DateTimeImmutable;
use Illuminate\Console\Command;

final class RunWeeklyPayouts extends Command
{
    protected $signature = 'payouts:run {--as-of= : Settle as if it were this date, defaults to today}';

    protected $description = 'Pay every seller the escrow released in the Monday-to-Sunday week that just ended';

    public function handle(RunWeeklyPayout $runWeeklyPayout): int
    {
        $asOf = new DateTimeImmutable($this->option('as-of') ?? 'now');
        $period = PayoutPeriod::endingBefore($asOf);

        $this->info("Payout period {$period->label()}");

        $payouts = $runWeeklyPayout($asOf);

        foreach ($payouts as $payout) {
            $this->line("{$payout->seller->displayName()} {$payout->amount()->format()}");
        }

        $this->info($this->summarise($payouts));

        return self::SUCCESS;
    }

    /**
     * @param  list<Payout>  $payouts
     */
    private function summarise(array $payouts): string
    {
        return $payouts === []
            ? 'No seller has a released balance for this period.'
            : count($payouts).' seller(s) paid.';
    }
}
