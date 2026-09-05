<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

use DateTimeImmutable;

/**
 * A seller creating and publishing one listing during the period —
 * `sellerSlot` and `templateIndex` are indices into whatever seller pool
 * and {@see ListingTemplates} entry `App\Console\Commands\SeedActivity`
 * resolves them against. `createdAt` and `publishedAt` are never the same
 * moment: a listing sits as a draft for at least a little while, the same
 * as a seller reviewing their own listing before it goes live.
 */
final readonly class NewListingStep
{
    public function __construct(
        public int $dayIndex,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $publishedAt,
        public int $sellerSlot,
        public int $templateIndex,
    ) {}
}
