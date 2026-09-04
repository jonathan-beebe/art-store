<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\PresenceStatus;

/**
 * One admin as the support hub shows them: their name, the shared desk
 * role, and the desk's current presence — every admin reads the same
 * presence in this cut, since it comes from one configured set of hours
 * rather than a schedule per person.
 */
final readonly class DeskPerson
{
    public function __construct(
        public string $name,
        public string $role,
        public PresenceStatus $presence,
        public string $presenceText,
    ) {}
}
