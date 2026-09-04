<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

use App\Domain\Auth\ActorType;
use App\Domain\DomainRuleViolation;
use DateTimeImmutable;

/**
 * One row about to be appended to a fulfillment's log, with the pairings the
 * log holds decided before anything is written: a transition names no step, a
 * step completion names one, and only a step that prints a label carries the
 * carrier and tracking number it printed with.
 */
final readonly class NewFulfillmentEvent
{
    private function __construct(
        public FulfillmentEventKind $kind,
        public ActorType $actorType,
        public string $actorId,
        public DateTimeImmutable $occurredAt,
        public ?FlowStep $step,
        public ?string $carrier,
        public ?string $trackingNumber,
    ) {}

    /**
     * @throws DomainRuleViolation when the kind names a step
     */
    public static function transition(
        FulfillmentEventKind $kind,
        ActorType $actorType,
        string $actorId,
        DateTimeImmutable $occurredAt,
    ): self {
        if ($kind->namesAStep()) {
            throw new DomainRuleViolation("A transition event cannot carry the kind {$kind->value}.");
        }

        return new self($kind, $actorType, $actorId, $occurredAt, null, null, null);
    }

    /**
     * @throws DomainRuleViolation when a step that prints no label carries
     *                             shipment details, or one that does carries none
     */
    public static function stepCompleted(
        FlowStep $step,
        ActorType $actorType,
        string $actorId,
        DateTimeImmutable $occurredAt,
        ?string $carrier = null,
        ?string $trackingNumber = null,
    ): self {
        $carrier = self::trimmed($carrier);
        $trackingNumber = self::trimmed($trackingNumber);
        $carriesShipment = $carrier !== null && $trackingNumber !== null;

        if ($step->printsLabel() && ! $carriesShipment) {
            throw new DomainRuleViolation("The step \"{$step->label}\" prints a label and needs a carrier and a tracking number.");
        }

        if (! $step->printsLabel() && ($carrier !== null || $trackingNumber !== null)) {
            throw new DomainRuleViolation("The step \"{$step->label}\" prints no label and carries no shipment details.");
        }

        return new self(
            FulfillmentEventKind::StepCompleted,
            $actorType,
            $actorId,
            $occurredAt,
            $step,
            $carrier,
            $trackingNumber,
        );
    }

    private static function trimmed(?string $value): ?string
    {
        $trimmed = trim($value ?? '');

        return $trimmed === '' ? null : $trimmed;
    }
}
