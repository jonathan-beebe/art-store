<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Seller;

/**
 * One name for the three actors a message thread can hold. A seller and an
 * admin already carry a `displayName()`; a customer has none, so a page that
 * names one reads it the way the admin site's customer pages already do —
 * their given name, or their id where they have not given one.
 */
final class ActorDisplay
{
    /**
     * How a seller or a customer sees the desk: every admin is one voice on
     * a support thread, so no single admin's name stands for it.
     */
    public const string SUPPORT_DESK = 'Art Store Support';

    private function __construct() {} // @codeCoverageIgnore

    public static function nameOf(Seller|Customer|Admin|null $actor): string
    {
        return match (true) {
            $actor instanceof Customer => $actor->name ?? 'Customer '.$actor->id,
            $actor instanceof Seller, $actor instanceof Admin => $actor->displayName(),
            default => 'Deleted account',
        };
    }
}
