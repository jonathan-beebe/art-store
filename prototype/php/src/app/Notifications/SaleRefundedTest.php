<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Money\Money;

it('goes to the in-app inbox by default', function (): void {
    $notification = new SaleRefunded('ord_00000000000000000000000004', Money::fromCents(10000), 'Dispute.');

    expect($notification->via($this->seller()))->toBe(['database']);
});

it('stores the amount and the reason an admin refunded it for', function (): void {
    $seller = $this->seller();
    $notification = new SaleRefunded('ord_00000000000000000000000004', Money::fromCents(10000), 'Dispute.');

    expect($notification->toArray($seller))->toBe([
        'subject' => 'Refund issued',
        'body' => 'A refund of $100.00 was issued on order ord_00000000000000000000000004. Reason: Dispute.',
        'url' => null,
    ]);
});

it('mails the same message with a link to the earnings page', function (): void {
    $notification = new SaleRefunded('ord_00000000000000000000000004', Money::fromCents(10000), 'Dispute.');
    $mail = $notification->toMail($this->seller());

    expect($mail->subject)->toBe('Refund issued')
        ->and($mail->actionUrl)->toBe(route('seller.earnings'));
});
