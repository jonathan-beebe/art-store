<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\Notification;
use App\Models\Seller;
use Illuminate\Auth\Access\Response;

/**
 * One notification row carries either a seller or a customer, so the
 * recipient column to compare depends on which site is asking.
 */
final class NotificationPolicy
{
    public function markRead(Seller|Customer $reader, Notification $notification): Response
    {
        $recipientId = $reader instanceof Seller
            ? $notification->seller_id
            : $notification->customer_id;

        return $recipientId === $reader->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
