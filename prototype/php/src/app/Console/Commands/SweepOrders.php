<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Orders\SweepStaleOrders;
use App\Models\Order;
use Illuminate\Console\Command;

final class SweepOrders extends Command
{
    /** @var string */
    protected $signature = 'orders:sweep';

    /** @var string */
    protected $description = 'Cancel the orders a guest never verified, once they are older than STALE_ORDER_HOURS';

    public function handle(SweepStaleOrders $sweepStaleOrders): int
    {
        $staleHours = (int) config('orders.stale_hours');

        $this->info("Cancelling orders left unverified for {$staleHours} hours");

        $cancelled = $sweepStaleOrders(now()->toDateTimeImmutable(), $staleHours);

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
