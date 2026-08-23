<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order's card was approved and its escrow is held.
 */
final readonly class OrderPaid
{
    use Dispatchable;

    public function __construct(public Order $order, public DateTimeImmutable $paidAt) {}
}
