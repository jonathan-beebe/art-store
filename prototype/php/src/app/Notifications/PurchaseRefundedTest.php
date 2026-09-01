<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Money\Money;
use App\Domain\Notifications\NotificationMessage;

it('goes to the in-app inbox by default', function (): void {
    $notification = new PurchaseRefunded('ord_00000000000000000000000004', Money::fromCents(10000), 'Damaged.');

    expect($notification->via($this->verifiedCustomer()))->toBe(['database']);
});

it('stores the amount and the reason it was refunded for', function (): void {
    $customer = $this->verifiedCustomer();
    $notification = new PurchaseRefunded('ord_00000000000000000000000004', Money::fromCents(10000), 'Damaged.');

    expect($notification->toArray($customer))
        ->toBe(NotificationMessage::purchaseRefunded('ord_00000000000000000000000004', Money::fromCents(10000), 'Damaged.')->toArray());
});

it('mails the same message with a link to the orders page', function (): void {
    $notification = new PurchaseRefunded('ord_00000000000000000000000004', Money::fromCents(10000), 'Damaged.');
    $mail = $notification->toMail($this->verifiedCustomer());

    expect($mail->subject)->toBe('Refund issued')
        ->and($mail->actionUrl)->toBe(route('shop.orders'));
});
