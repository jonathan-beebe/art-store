<?php

declare(strict_types=1);

namespace App\View;

use App\Domain\Messaging\ParticipantName;
use App\Domain\Seller\Initials;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Seller;

/**
 * One name for the three actors a message thread can hold, for a view that
 * holds the actor and needs the string. Each actor answers
 * `participantName()` itself; this adds the fallback for an account that is
 * gone and the initials a transcript avatar shows.
 */
final class ActorDisplay
{
    /**
     * How a seller or a customer sees the desk: every admin is one voice on
     * a support thread, so no single admin's name stands for it.
     */
    public const string SUPPORT_DESK = ParticipantName::DESK;

    private function __construct() {} // @codeCoverageIgnore

    public static function nameOf(Seller|Customer|Admin|null $actor): string
    {
        return $actor?->participantName() ?? ParticipantName::DELETED;
    }

    /**
     * Up to two initials for a transcript avatar — the first letter of each
     * of the first two words in `nameOf()`, the reduction the admin layout
     * already applies to the signed-in admin's own name.
     */
    public static function initialsOf(Seller|Customer|Admin|null $actor): string
    {
        return self::initialsFor(self::nameOf($actor));
    }

    /**
     * The same reduction over a name a page already holds — the support
     * desk's, or a buyer's read off the order that carried it.
     */
    public static function initialsFor(string $name): string
    {
        return Initials::of($name);
    }
}
