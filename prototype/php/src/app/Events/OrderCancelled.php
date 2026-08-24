<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order ended before it was ever paid — the customer said so, an admin
 * did, or the stale sweep did.
 */
final readonly class OrderCancelled
{
    use Dispatchable;

    public function __construct(public Order $order, public DateTimeImmutable $cancelledAt) {}
}
