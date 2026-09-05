<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Money\Money;
use App\Domain\Notifications\NotificationMessage;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells a customer one seller's share of their order was refunded, and why.
 */
final class PurchaseRefunded extends PrefixedUlidNotification
{
    public function __construct(
        private readonly string $orderId,
        private readonly Money $amount,
        private readonly string $reason,
    ) {
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
            ->action('Open your orders', route('shop.orders'));
    }

    private function message(): NotificationMessage
    {
        return NotificationMessage::purchaseRefunded($this->orderId, $this->amount, $this->reason);
    }
}
