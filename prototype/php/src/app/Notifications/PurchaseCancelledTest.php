<?php

declare(strict_types=1);

namespace App\Notifications;

it('goes to the in-app inbox by default', function (): void {
    expect((new PurchaseCancelled('ord_00000000000000000000000004'))->via($this->verifiedCustomer()))->toBe(['database']);
});

it('stores the subject, body, and url of the message', function (): void {
    $customer = $this->verifiedCustomer();

    expect((new PurchaseCancelled('ord_00000000000000000000000004'))->toArray($customer))->toBe([
        'subject' => 'Order cancelled',
        'body' => 'Order ord_00000000000000000000000004 was cancelled before it was paid. Nothing has been charged.',
        'url' => null,
    ]);
});

it('mails the same message with a link to the orders page', function (): void {
    $mail = (new PurchaseCancelled('ord_00000000000000000000000004'))->toMail($this->verifiedCustomer());

    expect($mail->subject)->toBe('Order cancelled')
        ->and($mail->actionUrl)->toBe(route('shop.orders'));
});
