<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Domain\Auth\ActorType;
use App\Domain\DomainRuleViolation;
use App\Domain\Fulfillment\NewFulfillmentEvent;
use App\Domain\Orders\FulfillmentStatus;
use App\Models\Fulfillment;
use App\Models\FulfillmentEvent;
use App\Models\FulfillmentFlowStep;
use App\Seller\FulfillmentFlowReader;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The seller says one step of their flow is behind them. The step it admits
 * is read inside the transaction that appends, against a fulfillment held for
 * update: a parcel shipped between the page and the submit is refused here,
 * and two submits of the same step leave one row.
 */
final readonly class CompleteFlowStep
{
    public function __construct(
        private AppendFulfillmentEvent $appendEvent,
        private FulfillmentFlowReader $flow,
    ) {}

    /**
     * @throws DomainRuleViolation when the parcel has left the studio, or the
     *                             step is not the one in front
     */
    public function __invoke(
        Fulfillment $fulfillment,
        FulfillmentFlowStep $step,
        ?string $carrier,
        ?string $trackingNumber,
        DateTimeImmutable $now,
    ): FulfillmentEvent {
        return DB::transaction(function () use ($fulfillment, $step, $carrier, $trackingNumber, $now): FulfillmentEvent {
            $this->assertAwaitingShipment($fulfillment->takeForTransition());
            $this->assertInFront($fulfillment, $step);

            return ($this->appendEvent)($fulfillment, NewFulfillmentEvent::stepCompleted(
                step: $step->toFlowStep(),
                actorType: ActorType::Seller,
                actorId: $fulfillment->seller_id,
                occurredAt: $now,
                carrier: $carrier,
                trackingNumber: $trackingNumber,
            ));
        });
    }

    private function assertAwaitingShipment(Fulfillment $fulfillment): void
    {
        if ($fulfillment->status !== FulfillmentStatus::AwaitingShipment) {
            throw new DomainRuleViolation("A step cannot be completed on a fulfillment that is {$fulfillment->status->value}.");
        }
    }

    private function assertInFront(Fulfillment $fulfillment, FulfillmentFlowStep $step): void
    {
        // Read after takeForTransition() has locked the row, so a step
        // submitted between this read and the lock cannot slip past the
        // same-step-twice guard.
        $fulfillment->load([
            'order.items.listing.fulfillmentFlow.steps',
            'seller.defaultFulfillmentFlow.steps',
            'fulfillmentEvents',
        ]);

        $progress = $this->flow->read($fulfillment)->progress;

        if (! $progress->admits($step->id)) {
            throw new DomainRuleViolation("The step \"{$step->label}\" is not the next step on this fulfillment.");
        }
    }
}
