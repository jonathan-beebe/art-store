<?php

declare(strict_types=1);

namespace App\Seller;

/**
 * One link of a seller navigation control — a segmented control's button,
 * a view switch's entry, a lane tab: what it says, where it goes, whether
 * it is the choice in force, and the count it wears when the control
 * carries one.
 */
final readonly class NavLink
{
    public function __construct(
        public string $label,
        public string $href,
        public bool $active,
        public ?int $count = null,
    ) {}
}
