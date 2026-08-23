<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Money\Money;
use App\Domain\Notifications\NotificationMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a seller one of their listings has been paid for.
 */
final class ItemSold extends Notification
{
    public function __construct(private readonly int $orderId, private readonly Money $net) {}

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
            ->action('Open the order', route('seller.orders.index'));
    }

    private function message(): NotificationMessage
    {
        return NotificationMessage::itemSold($this->orderId, $this->net);
    }
}
