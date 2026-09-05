<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

/**
 * How a thread names the people on it when the row itself has no name to
 * give: the desk, which every admin answers as one voice; a customer who
 * has given no name; a sender whose account is gone.
 */
final class ParticipantName
{
    public const string DESK = 'Art Store Support';

    public const string DELETED = 'Deleted account';

    private function __construct() {} // @codeCoverageIgnore

    /**
     * A customer's given name, or their id where they have given none. A
     * customer's email is never how the other side of a thread sees them.
     */
    public static function forCustomer(?string $name, string $id): string
    {
        return $name ?? 'Customer '.$id;
    }
}
