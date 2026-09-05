<?php

declare(strict_types=1);

namespace App\Console\Commands\Sweep;

use App\Actions\Orders\SweepStaleOrders;
use App\Console\Commands\Concerns\ReadsAsOf;
use App\Models\Order;
use Illuminate\Console\Command;
use Throwable;

final class SweepOrders extends Command
{
    use ReadsAsOf;

    /** @var string */
    protected $signature = 'sweep:orders {--as-of= : Sweep as if it were this date, defaults to today}';

    /** @var string */
    protected $description = 'Cancel the orders a guest never verified, once they are older than STALE_ORDER_HOURS';

    public function handle(SweepStaleOrders $sweepStaleOrders): int
    {
        $asOf = $this->asOf('the sweep can run as of');

        if ($asOf === null) {
            return self::FAILURE;
        }

        $staleHours = (int) config('orders.stale_hours');

        $this->info("Cancelling orders left unverified for {$staleHours} hours");

        try {
            $cancelled = $sweepStaleOrders($asOf, $staleHours);
        } catch (Throwable $e) {
            $this->error("order sweep failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        foreach ($cancelled as $order) {
            $this->line("{$order->id} {$order->total()->format()}");
        }

        $this->info($this->summarise($cancelled));

        return self::SUCCESS;
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
