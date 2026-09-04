<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

/**
 * One named step of a seller's fulfillment flow, as the core reads it: an
 * id to record a completion against, the words the seller gave it, what
 * completing it does, and where it sits in the order.
 */
final readonly class FlowStep
{
    public function __construct(
        public string $id,
        public string $key,
        public string $label,
        public FlowStepAction $action,
        public int $position,
    ) {}

    public function printsLabel(): bool
    {
        return $this->action->printsLabel();
    }
}
