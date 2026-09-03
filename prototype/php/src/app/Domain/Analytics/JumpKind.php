<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * Which entity a {@see \App\Analytics\Admin\Jump} names — what its caller
 * reads to pick the route it links to.
 */
enum JumpKind
{
    case Listing;
    case Actor;
}
