<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Fulfillment\FlowStep;
use App\Domain\Fulfillment\FulfillmentProgress;
use App\Domain\Fulfillment\ParcelState;

/**
 * Everything one order page reads beyond the parcel's own columns: where it
 * stands, where its money stands, the flow it ships by, and the card the
 * buyer paid with.
 */
final readonly class OrderFacts
{
    /**
     * @param  list<FlowStep>  $steps  the flow as it stands now, in position order
     * @param  array<string, CompletedStep>  $completed  keyed by the step each one completed
     */
    public function __construct(
        public ParcelState $state,
        public ?LedgerEntryType $escrow,
        public ?string $flowId,
        public string $flowName,
        public array $steps,
        public FulfillmentProgress $progress,
        public array $completed,
        public ?string $cardLastFour,
        public ?string $paymentStatus,
    ) {}
}
