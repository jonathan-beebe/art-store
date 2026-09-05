<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use App\Domain\Configurator\PricingMode;

/**
 * The one choice a create-screen shape adds to a fresh listing (DSGN-003):
 * the versions ramp adds a `standalone` choice with one priced option per
 * version; the extras ramp adds an `add_on` choice with one surcharged
 * option per extra. A shape with no choice creates a plain listing.
 */
final readonly class ListingCreationChoice
{
    /**
     * @param  list<array{label: string, cents: int}>  $rows  one option per row, in order; the first is the default
     */
    private function __construct(public string $name, public PricingMode $pricingMode, public array $rows) {}

    /**
     * @param  list<array{label: string, cents: int}>  $rows
     */
    public static function versions(string $name, array $rows): self
    {
        return new self($name, PricingMode::Standalone, $rows);
    }

    /**
     * @param  list<array{label: string, cents: int}>  $rows
     */
    public static function extras(string $name, array $rows): self
    {
        return new self($name, PricingMode::AddOn, $rows);
    }

    /**
     * A row's cents are the option's own price on a `standalone` choice and
     * its surcharge on an `add_on` one.
     */
    public function priceCentsOf(int $cents): ?int
    {
        return $this->pricingMode->isStandalone() ? $cents : null;
    }
}
