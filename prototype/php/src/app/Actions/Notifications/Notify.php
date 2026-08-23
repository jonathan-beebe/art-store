<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Domain\Notifications\NotificationMessage;
use App\Domain\Notifications\RecipientType;
use App\Models\Notification;

final readonly class Notify
{
    public function __invoke(RecipientType $recipient, int $recipientId, NotificationMessage $message): Notification
    {
        $notification = Notification::to($recipient, $recipientId, $message);

        $this->deliverByEmail($notification);

        return $notification;
    }

    /**
     * The prototype delivers to the in-app inbox only. Mail delivery hangs here,
     * behind the same port shape as MagicLinkDelivery.
     */
    private function deliverByEmail(Notification $notification): void {}
}
