<?php

namespace App\Actions\Notifications;

use App\Domain\Notifications\NotificationMessage;
use App\Domain\Notifications\RecipientType;
use App\Models\Notification;

final class Notify
{
    public function __invoke(RecipientType $recipient, int $recipientId, NotificationMessage $message): Notification
    {
        $notification = Notification::create([
            Notification::recipientColumn($recipient) => $recipientId,
            'subject' => $message->subject,
            'body' => $message->body,
            'url' => $message->url,
        ]);

        $this->deliverByEmail($notification);

        return $notification;
    }

    /**
     * The prototype delivers to the in-app inbox only. Mail delivery hangs here,
     * behind the same port shape as MagicLinkDelivery.
     */
    protected function deliverByEmail(Notification $notification): void {}
}
