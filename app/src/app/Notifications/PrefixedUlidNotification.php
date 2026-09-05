<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\IdMint;
use Illuminate\Notifications\Notification;

/**
 * A notification that names itself the way every other row in the database
 * does. `NotificationSender` mints a UUID only for a notification that
 * arrives without an id, so the id set here is the one the `notifications`
 * row is written under, on every channel it is delivered through. A
 * notification has no table of its own, so it draws its id from
 * {@see IdMint} — the same mint every other tableless id (a request, a
 * session) uses.
 */
abstract class PrefixedUlidNotification extends Notification
{
    private const string ID_PREFIX = 'ntf';

    public function __construct()
    {
        $this->id = IdMint::of(self::ID_PREFIX);
    }
}
