<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * One variant's facts, folded from its rows, for {@see ConfiguratorPublishValidation}
 * to judge without reading anything itself.
 */
final readonly class VariantSnapshot
{
    /**
     * @param  list<string>  $axisIdsCovered  the axes this variant holds one option value for
     */
    public function __construct(
        public string $id,
        public bool $enabled,
        public int $priceCents,
        public bool $isSerialized,
        public int $availableUnitCount,
        public array $axisIdsCovered,
    ) {}
}
