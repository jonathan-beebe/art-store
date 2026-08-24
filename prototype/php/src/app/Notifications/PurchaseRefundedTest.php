<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Money\Money;

it('goes to the in-app inbox by default', function (): void {
    $notification = new PurchaseRefunded('ord_00000000000000000000000004', Money::fromCents(10000), 'Damaged.');

    expect($notification->via($this->verifiedCustomer()))->toBe(['database']);
});

it('stores the amount and the reason it was refunded for', function (): void {
    $customer = $this->verifiedCustomer();
    $notification = new PurchaseRefunded('ord_00000000000000000000000004', Money::fromCents(10000), 'Damaged.');

    expect($notification->toArray($customer))->toBe([
        'subject' => 'Refund issued',
        'body' => '$100.00 of order ord_00000000000000000000000004 was refunded. Reason: Damaged.',
        'url' => null,
    ]);
});

it('mails the same message with a link to the orders page', function (): void {
    $notification = new PurchaseRefunded('ord_00000000000000000000000004', Money::fromCents(10000), 'Damaged.');
    $mail = $notification->toMail($this->verifiedCustomer());

    expect($mail->subject)->toBe('Refund issued')
        ->and($mail->actionUrl)->toBe(route('shop.orders'));
});
