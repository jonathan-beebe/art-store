<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

/**
 * One selected option value as the pricer reads it: the axis it answers,
 * how that axis prices (`docs/item-configurator.md` §3), and both of the
 * amounts the row carries. `amount()` picks the one the axis's mode makes
 * real — the absolute price on a `standalone` axis, the signed surcharge on
 * an `add_on` one.
 */
final readonly class PricedOption
{
    private function __construct(
        public string $id,
        public string $axisName,
        public string $label,
        public bool $standalone,
        public Money $price,
        public Money $surcharge,
    ) {}

    public static function of(string $id, string $axisName, string $label, bool $standalone, Money $price, Money $surcharge): self
    {
        return new self($id, $axisName, $label, $standalone, $price, $surcharge);
    }

    public function amount(): Money
    {
        return $this->standalone ? $this->price : $this->surcharge;
    }
}
