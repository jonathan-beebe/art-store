<?php

declare(strict_types=1);

namespace App\Notifications;

it('goes to the in-app inbox by default', function (): void {
    expect((new OrderShipped(4, 'USPS', '9400111899'))->via($this->verifiedCustomer()))->toBe(['database']);
});

it('stores the subject, body, and url of the message', function (): void {
    $customer = $this->verifiedCustomer();

    expect((new OrderShipped(4, 'USPS', '9400111899'))->toArray($customer))->toBe([
        'subject' => 'Order shipped',
        'body' => 'Order #4 shipped with USPS. Tracking number 9400111899.',
        'url' => null,
    ]);
});

it('mails the same message with a link to the orders page', function (): void {
    $mail = (new OrderShipped(4, 'USPS', '9400111899'))->toMail($this->verifiedCustomer());

    expect($mail->subject)->toBe('Order shipped')
        ->and($mail->introLines)->toBe(['Order #4 shipped with USPS. Tracking number 9400111899.'])
        ->and($mail->actionUrl)->toBe(route('shop.orders'));
});
