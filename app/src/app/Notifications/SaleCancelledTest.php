<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Notifications\NotificationMessage;

it('goes to the in-app inbox by default', function (): void {
    expect((new SaleCancelled('ord_00000000000000000000000004'))->via($this->seller()))->toBe(['database']);
});

it('stores the subject, body, and url of the message', function (): void {
    $seller = $this->seller();

    expect((new SaleCancelled('ord_00000000000000000000000004'))->toArray($seller))
        ->toBe(NotificationMessage::saleCancelled('ord_00000000000000000000000004')->toArray());
});

it('mails the same message with a link to the seller orders page', function (): void {
    $mail = (new SaleCancelled('ord_00000000000000000000000004'))->toMail($this->seller());

    expect($mail->subject)->toBe('Order cancelled')
        ->and($mail->actionUrl)->toBe(route('seller.orders.index'));
});
