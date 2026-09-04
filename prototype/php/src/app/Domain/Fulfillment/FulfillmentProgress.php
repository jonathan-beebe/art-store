<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

/**
 * How far one parcel has come through its flow: the steps behind it, the one
 * in front of it, and whether anything is left. The completed set is read
 * against the flow as it stands now, so a step the seller has since removed
 * leaves the steps after it where they were.
 */
final readonly class FulfillmentProgress
{
    /**
     * @param  list<FlowStep>  $completed
     * @param  list<FlowStep>  $remaining
     */
    private function __construct(
        public array $completed,
        public array $remaining,
    ) {}

    /**
     * @param  list<FlowStep>  $steps  the flow in position order
     * @param  list<string>  $completedStepIds
     */
    public static function of(array $steps, array $completedStepIds): self
    {
        $done = array_flip($completedStepIds);

        $completed = array_values(array_filter($steps, fn (FlowStep $step): bool => isset($done[$step->id])));
        $remaining = array_values(array_filter($steps, fn (FlowStep $step): bool => ! isset($done[$step->id])));

        return new self($completed, $remaining);
    }

    /**
     * The step the seller does next: the first one no event names. An empty
     * flow and a finished one both have none.
     */
    public function next(): ?FlowStep
    {
        return $this->remaining[0] ?? null;
    }

    /**
     * Whether $stepId is the step in front. Completing any other step is out
     * of order.
     */
    public function admits(string $stepId): bool
    {
        return $this->next()?->id === $stepId;
    }

    public function hasStarted(): bool
    {
        return $this->completed !== [];
    }

    public function isDone(): bool
    {
        return $this->remaining === [];
    }

    public function completedCount(): int
    {
        return count($this->completed);
    }

    public function stepCount(): int
    {
        return count($this->completed) + count($this->remaining);
    }
}
