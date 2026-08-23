<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\Seller;
use Illuminate\Auth\Access\Response;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A notification row names its recipient by morph type and id, so both the
 * side asking and the row's owner have to match.
 */
final class NotificationPolicy
{
    public function markRead(Seller|Customer $reader, DatabaseNotification $notification): Response
    {
        $isRecipient = $notification->notifiable_type === $reader->getMorphClass()
            && $notification->notifiable_id === $reader->id;

        return $isRecipient
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
