<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Notifications\NotificationMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a customer one seller's share of their order is with a carrier.
 */
final class OrderShipped extends Notification
{
    public function __construct(
        private readonly int $orderId,
        private readonly string $carrier,
        private readonly string $trackingNumber,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return config('notifications.channels');
    }

    /**
     * @return array{subject: string, body: string, url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return $this->message()->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->message();

        return (new MailMessage)
            ->subject($message->subject)
            ->line($message->body)
            ->action('Open your order', route('shop.orders'));
    }

    private function message(): NotificationMessage
    {
        return NotificationMessage::orderShipped($this->orderId, $this->carrier, $this->trackingNumber);
    }
}
