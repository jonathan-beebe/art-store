<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Escrow\RunWeeklyPayout;
use App\Domain\Escrow\PayoutPeriod;
use App\Models\Payout;
use DateMalformedStringException;
use DateTimeImmutable;
use Illuminate\Console\Command;

final class RunWeeklyPayouts extends Command
{
    /** @var string */
    protected $signature = 'payouts:run {--as-of= : Settle as if it were this date, defaults to today}';

    /** @var string */
    protected $description = 'Pay every seller the escrow released in the Monday-to-Sunday week that just ended';

    public function handle(RunWeeklyPayout $runWeeklyPayout): int
    {
        $rawAsOf = $this->option('as-of');
        $asOfInput = is_string($rawAsOf) && $rawAsOf !== '' ? $rawAsOf : null;

        try {
            $asOf = $asOfInput === null ? now()->toDateTimeImmutable() : new DateTimeImmutable($asOfInput);
        } catch (DateMalformedStringException) {
            $this->error("\"{$asOfInput}\" is not a date payouts can settle as of.");

            return self::FAILURE;
        }

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
