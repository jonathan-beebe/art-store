<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

use App\Domain\DomainRuleViolation;

/**
 * One row of a flow as the seller submits it. An id names a step already in
 * the flow, which a rename keeps: the events pointing at it stay pointing at
 * it. No id is a step the flow has never had.
 */
final readonly class FlowStepDraft
{
    public const int LABEL_LIMIT = 60;

    private function __construct(
        public ?string $id,
        public string $label,
        public FlowStepAction $action,
    ) {}

    /**
     * @throws DomainRuleViolation when the words are blank or longer than a step's label holds
     */
    public static function of(?string $id, string $label, FlowStepAction $action): self
    {
        $label = trim($label);

        if ($label === '') {
            throw new DomainRuleViolation('A step needs a name.');
        }

        if (mb_strlen($label) > self::LABEL_LIMIT) {
            throw new DomainRuleViolation('A step name is at most '.self::LABEL_LIMIT.' characters.');
        }

        return new self($id, $label, $action);
    }

    public function isNew(): bool
    {
        return $this->id === null;
    }
}
