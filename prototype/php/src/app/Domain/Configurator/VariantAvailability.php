<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * Whether a variant can be bought right now: disabled beats everything else,
 * a serialized variant needs at least one unit still `available`, and an
 * ordinary one needs no tracked quantity or a positive one — the same
 * `ListingStock` shape one level down, so a variant with no stock tracking
 * behaves like today's untracked listing.
 */
final readonly class VariantAvailability
{
    private function __construct(public bool $available) {}

    public static function resolve(bool $enabled, bool $isSerialized, int $availableUnitCount, ?int $quantity): self
    {
        if (! $enabled) {
            return new self(false);
        }

        if ($isSerialized) {
            return new self($availableUnitCount > 0);
        }

        return new self($quantity === null || $quantity > 0);
    }
}
