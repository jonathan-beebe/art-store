<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Notifications\NotificationMessage;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells a seller an order carrying their pieces was cancelled before it was
 * paid, so the stock is back on their storefront.
 */
final class SaleCancelled extends PrefixedUlidNotification
{
    public function __construct(private readonly string $orderId)
    {
        parent::__construct();
    }

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
            ->action('Open your orders', route('seller.orders.index'));
    }

    private function message(): NotificationMessage
    {
        return NotificationMessage::saleCancelled($this->orderId);
    }
}
