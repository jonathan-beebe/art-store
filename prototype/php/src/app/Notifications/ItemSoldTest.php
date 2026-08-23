<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Money\Money;

it('goes to the in-app inbox by default', function (): void {
    $seller = $this->seller();

    expect((new ItemSold(4, Money::fromCents(9000)))->via($seller))->toBe(['database']);
});

it('follows the channels the config names', function (): void {
    config(['notifications.channels' => ['database', 'mail']]);

    expect((new ItemSold(4, Money::fromCents(9000)))->via($this->seller()))->toBe(['database', 'mail']);
});

it('stores the subject, body, and url of the message', function (): void {
    $seller = $this->seller();

    expect((new ItemSold(4, Money::fromCents(9000)))->toArray($seller))->toBe([
        'subject' => 'Item sold',
        'body' => 'Order #4 is paid. $90.00 is held until the customer confirms delivery.',
        'url' => null,
    ]);
});

it('mails the same message with a link to the orders page', function (): void {
    $seller = $this->seller();

    $mail = (new ItemSold(4, Money::fromCents(9000)))->toMail($seller);

    expect($mail->subject)->toBe('Item sold')
        ->and($mail->introLines)->toBe(['Order #4 is paid. $90.00 is held until the customer confirms delivery.'])
        ->and($mail->actionUrl)->toBe(route('seller.orders.index'));
});
