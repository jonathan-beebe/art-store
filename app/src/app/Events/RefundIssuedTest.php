<?php

declare(strict_types=1);

namespace App\Events;

use App\Actions\Fulfillment\DeclineFulfillment;
use Illuminate\Support\Facades\Event;

it('is raised when money goes back to a customer', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller(), priceCents: 10000);
    Event::fake([RefundIssued::class]);

    app(DeclineFulfillment::class)($fulfillment, 'Damaged.', $this->moment('2026-08-21 09:00:00'));

    Event::assertDispatched(
        RefundIssued::class,
        fn (RefundIssued $event): bool => $event->refund->fulfillment_id === $fulfillment->id
            && $event->refund->amount_cents === 10000
            && $event->issuedAt->format('Y-m-d H:i:s') === '2026-08-21 09:00:00',
    );
});
