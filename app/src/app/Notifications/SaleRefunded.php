<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Money\Money;
use App\Domain\Notifications\NotificationMessage;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells a seller an admin refunded one of their sales, and why. A seller who
 * declined the parcel themselves is not told what they just did.
 */
final class SaleRefunded extends PrefixedUlidNotification
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
            ->action('Open your earnings', route('seller.earnings'));
    }

    private function message(): NotificationMessage
    {
        return NotificationMessage::saleRefunded($this->orderId, $this->amount, $this->reason);
    }
}
