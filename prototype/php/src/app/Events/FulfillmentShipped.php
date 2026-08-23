<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Fulfillment;
use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * One seller's share of an order left with a carrier.
 */
final readonly class FulfillmentShipped
{
    use Dispatchable;

    public function __construct(public Fulfillment $fulfillment, public DateTimeImmutable $shippedAt) {}
}
