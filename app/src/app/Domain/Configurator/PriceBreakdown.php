<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

/**
 * The itemized price a buyer sees for one configuration: the variant price,
 * every priced modifier answer, and the quantity discount if one applies —
 * the same breakdown the price panel shows and the receipt later snapshots.
 */
final readonly class PriceBreakdown
{
    /**
     * @param  list<PriceBreakdownLine>  $lines
     */
    private function __construct(public array $lines) {}

    /**
     * @param  list<PriceBreakdownLine>  $lines
     */
    public static function of(array $lines): self
    {
        return new self($lines);
    }

    public function total(): Money
    {
        $total = Money::zero();

        foreach ($this->lines as $line) {
            $total = $total->add($line->amount);
        }

        return $total;
    }
}
