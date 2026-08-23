<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class NotificationMessageTest extends TestCase
{
    public function test_a_sale_tells_the_seller_what_is_held_for_them(): void
    {
        $message = NotificationMessage::itemSold(12, Money::fromCents(9000));

        $this->assertSame('Item sold', $message->subject);
        $this->assertStringContainsString('Order #12', $message->body);
        $this->assertStringContainsString('$90.00', $message->body);
        $this->assertNull($message->url);
    }

    public function test_a_shipment_tells_the_customer_who_is_carrying_it(): void
    {
        $message = NotificationMessage::orderShipped(12, 'USPS', '9400111899');

        $this->assertSame('Order shipped', $message->subject);
        $this->assertStringContainsString('Order #12', $message->body);
        $this->assertStringContainsString('USPS', $message->body);
        $this->assertStringContainsString('9400111899', $message->body);
    }

    public function test_a_message_takes_a_link_without_losing_its_text(): void
    {
        $message = NotificationMessage::itemSold(12, Money::fromCents(9000))->withUrl('/seller/orders/12');

        $this->assertSame('/seller/orders/12', $message->url);
        $this->assertSame('Item sold', $message->subject);
    }
}
