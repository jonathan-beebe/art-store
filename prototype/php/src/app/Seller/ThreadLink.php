<?php

declare(strict_types=1);

namespace App\Seller;

/**
 * One of a buyer's other threads as the context rail lists it: what it is
 * about, where it opens, and how long ago it was last spoken in.
 */
final readonly class ThreadLink
{
    public function __construct(
        public string $title,
        public string $href,
        public ?string $when,
    ) {}
}
