<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Domain\Money\Money;

final readonly class NotificationMessage
{
    private function __construct(public string $subject, public string $body, public ?string $url) {}

    public static function itemSold(string $orderId, Money $net): self
    {
        return new self(
            'Item sold',
            "Order {$orderId} is paid. {$net->format()} is held until the customer confirms delivery.",
            null,
        );
    }

    public static function orderShipped(string $orderId, string $carrier, string $trackingNumber): self
    {
        return new self(
            'Order shipped',
            "Order {$orderId} shipped with {$carrier}. Tracking number {$trackingNumber}.",
            null,
        );
    }

    /**
     * $url names the recipient's thread on their own site. Nullable for the
     * same reason every factory here is: an inbox row can carry no link.
     */
    public static function messageReceived(string $topic, ?string $url): self
    {
        return new self(
            'New message',
            "You have a new message about {$topic}.",
            $url,
        );
    }

    /**
     * The shape an inbox row stores and a page reads back.
     *
     * @return array{subject: string, body: string, url: string|null}
     */
    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
