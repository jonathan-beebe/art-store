<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Fulfillment\FulfillmentProgress;
use App\Models\Fulfillment;
use App\Models\FulfillmentEvent;
use App\Models\FulfillmentFlow;

/**
 * The flow one parcel ships by, and how far it has come through it, in one
 * read. Reads `order.items.listing.fulfillmentFlow.steps`,
 * `seller.defaultFulfillmentFlow.steps`, and `fulfillmentEvents`; a caller
 * that has not eager-loaded them is refused by the lazy-loading guard.
 */
final readonly class FulfillmentFlowReader
{
    public function read(Fulfillment $fulfillment): FulfillmentFlowFacts
    {
        $flow = $this->flowInEffect($fulfillment);
        $steps = $flow instanceof FulfillmentFlow ? $flow->flowSteps() : [];

        return new FulfillmentFlowFacts(
            flow: $flow,
            steps: $steps,
            progress: FulfillmentProgress::of($steps, $this->completedStepIds($fulfillment)),
        );
    }

    /**
     * The flow this parcel ships by: the one the first of the seller's own
     * lines names, and the seller's default flow when none does.
     */
    private function flowInEffect(Fulfillment $fulfillment): ?FulfillmentFlow
    {
        return $this->flowNamedByAListing($fulfillment) ?? $fulfillment->seller->defaultFulfillmentFlow;
    }

    private function flowNamedByAListing(Fulfillment $fulfillment): ?FulfillmentFlow
    {
        foreach ($fulfillment->order->items as $item) {
            if ($item->seller_id === $fulfillment->seller_id && $item->listing->fulfillmentFlow instanceof FulfillmentFlow) {
                return $item->listing->fulfillmentFlow;
            }
        }

        return null;
    }

    /**
     * One entry per completion the log holds, carrying the step it named. A
     * step the seller has since removed leaves a null: the row survives with
     * its `step_label`, so the parcel still counts as started.
     *
     * @return list<string|null>
     */
    private function completedStepIds(Fulfillment $fulfillment): array
    {
        return array_values($fulfillment->fulfillmentEvents
            ->where('kind', FulfillmentEventKind::StepCompleted)
            ->map(fn (FulfillmentEvent $event): ?string => $event->fulfillment_flow_step_id)
            ->all());
    }
}
