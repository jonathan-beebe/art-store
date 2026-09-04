<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * One `standalone`-axis option's price, folded from its row, for
 * {@see ConfiguratorPublishValidation} to judge without reading anything
 * itself. `priceCents` is `null` for a row that never got a price — the
 * state {@see \App\Actions\Configurator\AddOptionValue} and
 * {@see \App\Actions\Configurator\UpdateOptionValue} refuse to write, kept
 * here as a defensive publish-time check on whatever the row actually holds.
 */
final readonly class StandaloneOptionSnapshot
{
    public function __construct(public string $id, public ?int $priceCents) {}
}
