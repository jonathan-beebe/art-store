<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Fulfillment\RefundFulfillment;
use App\Events\RefundIssued;
use App\Models\Refund;
use App\Notifications\PurchaseRefunded;
use App\Notifications\SaleRefunded;
use Illuminate\Support\Facades\Notification;

it('tells the customer why a seller declined their parcel', function (): void {
    $customer = $this->verifiedCustomer();
    $fulfillment = $this->paidFulfillmentFor($this->seller(), $customer, priceCents: 10000);
    app(DeclineFulfillment::class)($fulfillment, 'The kiln cracked the glaze.', $this->moment('2026-08-21 09:00:00'));
    Notification::fake();

    app(NotifyOfRefund::class)->handle(new RefundIssued(Refund::sole(), $this->moment('2026-08-21 09:00:00')));

    Notification::assertSentTo(
        $customer,
        PurchaseRefunded::class,
        fn (PurchaseRefunded $notification): bool => $notification->toArray($customer)['body']
            === "\$100.00 of order {$fulfillment->order_id} was refunded. Reason: The kiln cracked the glaze.",
    );
});

it('leaves the seller who declined out of it', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    app(DeclineFulfillment::class)($fulfillment, 'Damaged.', $this->moment('2026-08-21 09:00:00'));
    Notification::fake();

    app(NotifyOfRefund::class)->handle(new RefundIssued(Refund::sole(), $this->moment('2026-08-21 09:00:00')));

    Notification::assertNotSentTo($seller, SaleRefunded::class);
});

it('tells the seller when an admin refunded their sale', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->deliveredFulfillmentFor($seller, priceCents: 10000);
    app(RefundFulfillment::class)($fulfillment, $this->admin(), 'Dispute.', $this->moment('2026-08-23 09:00:00'));
    Notification::fake();

    app(NotifyOfRefund::class)->handle(new RefundIssued(Refund::sole(), $this->moment('2026-08-23 09:00:00')));

    Notification::assertSentTo(
        $seller,
        SaleRefunded::class,
        fn (SaleRefunded $notification): bool => $notification->toArray($seller)['body']
            === "A refund of \$100.00 was issued on order {$fulfillment->order_id}. Reason: Dispute.",
    );
});
