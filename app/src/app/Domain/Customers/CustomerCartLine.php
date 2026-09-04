<?php

declare(strict_types=1);

namespace App\Domain\Customers;

/**
 * One cart row, stripped down to what `CustomerMergePlan` needs to fold two
 * carts together. `App\Domain\Cart\CartLine` carries a seller and a price
 * too, for totalling a cart that is already settled on one owner — the merge
 * runs before that, so it works from less. Two lines for the same listing
 * merge only when their fingerprint also matches, the same rule
 * `App\Actions\Cart\AddToCart` folds a duplicate add by — a legacy zero-axis
 * listing carries the same constant fingerprint on both sides of a merge, so
 * it still sums the way it always has.
 */
final readonly class CustomerCartLine
{
    /**
     * @param  list<array{axisId: string, axisName: string, optionValueId: string, optionValueLabel: string}>|null  $configurationJson
     * @param  array<string, array{prompt: string, answer: string, raw: string}>|null  $answersJson
     */
    public function __construct(
        public string $listingId,
        public int $quantity,
        public string $fingerprint = '',
        public ?string $variantId = null,
        public ?string $unitId = null,
        public ?array $configurationJson = null,
        public ?array $answersJson = null,
    ) {}
}
