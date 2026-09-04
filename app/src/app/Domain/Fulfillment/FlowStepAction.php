<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

/**
 * What completing a step does beyond recording that it happened.
 */
enum FlowStepAction: string
{
    case None = 'none';
    case PrintLabel = 'print_label';

    public function printsLabel(): bool
    {
        return $this === self::PrintLabel;
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'Record it only',
            self::PrintLabel => 'Print a shipping label',
        };
    }

    /**
     * The verb the step's own control carries.
     */
    public function control(): string
    {
        return match ($this) {
            self::None => 'Mark done',
            self::PrintLabel => 'Print label',
        };
    }
}
