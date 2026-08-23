<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\Money;
use App\Domain\Notifications\NotificationMessage;
use App\Domain\Notifications\RecipientType;

it('addresses a row to the seller column', function (): void {
    $seller = $this->seller();

    $notification = Notification::to(RecipientType::Seller, $seller->id, NotificationMessage::itemSold(4, Money::fromCents(9000)));

    expect($notification)
        ->seller_id->toBe($seller->id)
        ->customer_id->toBeNull()
        ->subject->toBe('Item sold')
        ->url->toBeNull()
        ->read_at->toBeNull();
});

it('addresses a row to the customer column', function (): void {
    $customer = $this->verifiedCustomer();

    $notification = Notification::to(
        RecipientType::Customer,
        $customer->id,
        NotificationMessage::orderShipped(4, 'USPS', '94001')->withUrl('/orders/4'),
    );

    expect($notification)
        ->customer_id->toBe($customer->id)
        ->seller_id->toBeNull()
        ->url->toBe('/orders/4');
});

it('narrows to the unread rows of one recipient', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    Notification::to(RecipientType::Seller, $seller->id, NotificationMessage::itemSold(4, Money::fromCents(9000)));
    $read = Notification::to(RecipientType::Seller, $seller->id, NotificationMessage::itemSold(5, Money::fromCents(9000)));
    $read->update(['read_at' => now()]);
    Notification::to(RecipientType::Customer, $customer->id, NotificationMessage::orderShipped(4, 'USPS', '94001'));

    expect($seller->notifications()->count())->toBe(2)
        ->and($seller->notifications()->unread()->count())->toBe(1);
});
