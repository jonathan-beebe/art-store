<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Domain\Money\Money;
use App\Domain\Notifications\NotificationMessage;
use App\Domain\Notifications\RecipientType;
use App\Models\Notification;

it('writes a row addressed to a seller', function (): void {
    $seller = $this->seller();

    $notification = app(Notify::class)(
        RecipientType::Seller,
        $seller->id,
        NotificationMessage::itemSold(4, Money::fromCents(9000)),
    );

    expect($notification)
        ->seller_id->toBe($seller->id)
        ->customer_id->toBeNull()
        ->subject->toBe('Item sold')
        ->read_at->toBeNull();
});

it('writes a row addressed to a customer', function (): void {
    $customer = $this->verifiedCustomer();

    $notification = app(Notify::class)(
        RecipientType::Customer,
        $customer->id,
        NotificationMessage::orderShipped(4, 'USPS', '9400111899')->withUrl('/orders/4'),
    );

    expect($notification)
        ->customer_id->toBe($customer->id)
        ->seller_id->toBeNull()
        ->url->toBe('/orders/4');
});

it('shows an unread notification for its recipient only', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $notify = app(Notify::class);

    $notify(RecipientType::Seller, $seller->id, NotificationMessage::itemSold(4, Money::fromCents(9000)));
    $notify(RecipientType::Customer, $customer->id, NotificationMessage::orderShipped(4, 'USPS', '94001'));

    expect(Notification::query()->unread()->for(RecipientType::Seller, $seller->id)->count())->toBe(1)
        ->and(Notification::query()->unread()->for(RecipientType::Customer, $customer->id)->count())->toBe(1);
});
