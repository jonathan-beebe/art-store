<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * One axis's option value ids and which one wins when the buyer has not
 * chosen — the seller's `is_default`, or the first by position — the
 * primitive {@see AxisSelectionResolver} resolves every axis's selection
 * against.
 */
final readonly class AxisDefaults
{
    /**
     * @param  list<string>  $optionValueIds
     */
    private function __construct(public string $axisId, public array $optionValueIds, public string $defaultOptionValueId) {}

    /**
     * @param  list<string>  $optionValueIds
     */
    public static function of(string $axisId, array $optionValueIds, string $defaultOptionValueId): self
    {
        return new self($axisId, $optionValueIds, $defaultOptionValueId);
    }
}
