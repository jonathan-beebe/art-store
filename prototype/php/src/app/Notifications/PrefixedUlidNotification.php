<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Identifiers\PrefixedId;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * A notification that names itself the way every other row in the database
 * does. `NotificationSender` mints a UUID only for a notification that
 * arrives without an id, so the id set here is the one the `notifications`
 * row is written under, on every channel it is delivered through.
 */
abstract class PrefixedUlidNotification extends Notification
{
    private const string ID_PREFIX = 'ntf';

    public function __construct()
    {
        $this->id = (string) PrefixedId::of(self::ID_PREFIX, (string) Str::ulid(Date::now()));
    }
}
