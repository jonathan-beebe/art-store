<?php

declare(strict_types=1);

namespace App\Seller;

/**
 * One control of a feed's kind filter: what it narrows to, the link, and
 * whether it is the current one.
 */
final readonly class FeedKindLink
{
    public function __construct(
        public string $label,
        public string $href,
        public bool $active,
    ) {}
}
