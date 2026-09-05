<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

/**
 * How far one parcel has come through its flow: the steps behind it, the one
 * in front of it, and whether anything is left. Which steps are behind it is
 * read against the flow as it stands now, so a step the seller has since
 * removed leaves the steps after it where they were. Whether the parcel has
 * started is read against the completions themselves, so removing a step the
 * seller had already done never walks the parcel back to the top of the flow.
 */
final readonly class FulfillmentProgress
{
    /**
     * @param  list<FlowStep>  $completed
     * @param  list<FlowStep>  $remaining
     * @param  int  $completionCount  every completion, the ones naming a step the flow no longer holds included
     */
    private function __construct(
        public array $completed,
        public array $remaining,
        public int $completionCount,
    ) {}

    /**
     * @param  list<FlowStep>  $steps  the flow in position order
     * @param  list<string|null>  $completedStepIds  one per completion; null where the step is gone
     */
    public static function of(array $steps, array $completedStepIds): self
    {
        $done = array_flip(array_values(array_filter($completedStepIds, is_string(...))));

        $completed = array_values(array_filter($steps, fn (FlowStep $step): bool => isset($done[$step->id])));
        $remaining = array_values(array_filter($steps, fn (FlowStep $step): bool => ! isset($done[$step->id])));

        return new self($completed, $remaining, count($completedStepIds));
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

    /**
     * Whether anything has been done to this parcel. A completion whose step
     * the seller has since removed still counts: the log kept it.
     */
    public function hasStarted(): bool
    {
        return $this->completionCount > 0;
    }

    public function hasCompleted(FlowStep $step): bool
    {
        foreach ($this->completed as $done) {
            if ($done->id === $step->id) {
                return true;
            }
        }

        return false;
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
