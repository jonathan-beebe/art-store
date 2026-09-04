<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Fulfillment\FlowStep;
use App\Domain\Fulfillment\FulfillmentProgress;
use App\Models\FulfillmentFlow;

/**
 * The flow one parcel ships by, read once by {@see FulfillmentFlowReader}:
 * the flow itself (`null` when the seller has none), its steps in position
 * order, and how far the parcel has come through them.
 */
final readonly class FulfillmentFlowFacts
{
    /**
     * @param  list<FlowStep>  $steps
     */
    public function __construct(
        public ?FulfillmentFlow $flow,
        public array $steps,
        public FulfillmentProgress $progress,
    ) {}
}
