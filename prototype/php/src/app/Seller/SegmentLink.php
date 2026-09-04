<?php

declare(strict_types=1);

namespace App\Seller;

/**
 * One button of a segmented control: what it says, where it goes, and
 * whether it is the choice in force.
 */
final readonly class SegmentLink
{
    public function __construct(
        public string $label,
        public string $href,
        public bool $active,
    ) {}
}
