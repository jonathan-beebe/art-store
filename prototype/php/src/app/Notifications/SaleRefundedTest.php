<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Money\Money;
use App\Domain\Notifications\NotificationMessage;

it('goes to the in-app inbox by default', function (): void {
    $notification = new SaleRefunded('ord_00000000000000000000000004', Money::fromCents(10000), 'Dispute.');

    expect($notification->via($this->seller()))->toBe(['database']);
});

it('stores the amount and the reason an admin refunded it for', function (): void {
    $seller = $this->seller();
    $notification = new SaleRefunded('ord_00000000000000000000000004', Money::fromCents(10000), 'Dispute.');

    expect($notification->toArray($seller))
        ->toBe(NotificationMessage::saleRefunded('ord_00000000000000000000000004', Money::fromCents(10000), 'Dispute.')->toArray());
});

it('mails the same message with a link to the earnings page', function (): void {
    $notification = new SaleRefunded('ord_00000000000000000000000004', Money::fromCents(10000), 'Dispute.');
    $mail = $notification->toMail($this->seller());

    expect($mail->subject)->toBe('Refund issued')
        ->and($mail->actionUrl)->toBe(route('seller.earnings'));
});
