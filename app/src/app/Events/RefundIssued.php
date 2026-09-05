<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Refund;
use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * One fulfillment's money went back to the customer, because the seller
 * declined it or an admin refunded it.
 */
final readonly class RefundIssued
{
    use Dispatchable;

    public function __construct(public Refund $refund, public DateTimeImmutable $issuedAt) {}
}
