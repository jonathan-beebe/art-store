<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

/**
 * One row of an entity page's identity card — {@see EntityActivity}'s
 * label/value pair for the `dl` beside the badge and title.
 */
final readonly class EntityFact
{
    public function __construct(
        public string $label,
        public string $value,
    ) {}
}
