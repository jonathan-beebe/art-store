<?php

namespace App\Actions\Notifications;

use App\Domain\Money\Money;
use App\Domain\Notifications\NotificationMessage;
use App\Domain\Notifications\RecipientType;
use App\Models\Notification;
use Tests\CommerceTestCase;

final class NotifyTest extends CommerceTestCase
{
    public function test_it_writes_a_row_addressed_to_a_seller(): void
    {
        $seller = $this->seller();

        $notification = app(Notify::class)(
            RecipientType::Seller,
            $seller->id,
            NotificationMessage::itemSold(4, Money::fromCents(9000)),
        );

        $this->assertSame($seller->id, $notification->seller_id);
        $this->assertNull($notification->customer_id);
        $this->assertSame('Item sold', $notification->subject);
        $this->assertNull($notification->read_at);
    }

    public function test_it_writes_a_row_addressed_to_a_customer(): void
    {
        $customer = $this->verifiedCustomer();

        $notification = app(Notify::class)(
            RecipientType::Customer,
            $customer->id,
            NotificationMessage::orderShipped(4, 'USPS', '9400111899')->withUrl('/orders/4'),
        );

        $this->assertSame($customer->id, $notification->customer_id);
        $this->assertNull($notification->seller_id);
        $this->assertSame('/orders/4', $notification->url);
    }

    public function test_an_unread_notification_shows_up_for_its_recipient_only(): void
    {
        $seller = $this->seller();
        $customer = $this->verifiedCustomer();
        $notify = app(Notify::class);

        $notify(RecipientType::Seller, $seller->id, NotificationMessage::itemSold(4, Money::fromCents(9000)));
        $notify(RecipientType::Customer, $customer->id, NotificationMessage::orderShipped(4, 'USPS', '94001'));

        $this->assertSame(1, Notification::query()->unread()->for(RecipientType::Seller, $seller->id)->count());
        $this->assertSame(1, Notification::query()->unread()->for(RecipientType::Customer, $customer->id)->count());
    }
}
