<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Notifications\NotificationMessage;

it('goes to the in-app inbox by default', function (): void {
    expect((new OrderShipped('ord_00000000000000000000000004', 'USPS', '9400111899'))->via($this->verifiedCustomer()))->toBe(['database']);
});

it('stores the subject, body, and url of the message', function (): void {
    $customer = $this->verifiedCustomer();

    expect((new OrderShipped('ord_00000000000000000000000004', 'USPS', '9400111899'))->toArray($customer))
        ->toBe(NotificationMessage::orderShipped('ord_00000000000000000000000004', 'USPS', '9400111899')->toArray());
});

it('mails the same message with a link to the orders page', function (): void {
    $mail = (new OrderShipped('ord_00000000000000000000000004', 'USPS', '9400111899'))->toMail($this->verifiedCustomer());

    expect($mail->subject)->toBe('Order shipped')
        ->and($mail->introLines)->toBe(['Order ord_00000000000000000000000004 shipped with USPS. Tracking number 9400111899.'])
        ->and($mail->actionUrl)->toBe(route('shop.orders'));
});
