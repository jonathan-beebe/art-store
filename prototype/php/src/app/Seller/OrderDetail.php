<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Auth\ActorType;
use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Fulfillment\DefaultFlow;
use App\Domain\Fulfillment\FlowStep;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Fulfillment\ParcelState;
use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use App\Models\Fulfillment;
use App\Models\FulfillmentEvent;
use App\Models\FulfillmentFlow;
use App\Models\LedgerEntry;
use App\Models\Seller;
use App\Support\ActorDisplay;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * One order page's reads, in one pass. The page asks a parcel a dozen
 * questions — where it stands, where its money stands, which steps are
 * behind it and who marked them — and this loads the relations all of them
 * need once, then hands plain values to the pure core.
 */
final readonly class OrderDetail
{
    public function __construct(private FulfillmentFlowReader $flow) {}

    /**
     * @param  Seller  $seller  the signed-in seller, whose own lines the page shows
     */
    public function facts(Fulfillment $fulfillment, Seller $seller, DateTimeImmutable $now): OrderFacts
    {
        $fulfillment->load([
            'customer',
            'order.items' => fn (Relation $items) => $items->where('seller_id', $seller->id),
            'order.items.listing.fulfillmentFlow.steps',
            'order.latestPayment',
            'ledgerEntries',
            'refund',
            'fulfillmentEvents',
            'fulfillmentFlow.steps',
            'seller.defaultFulfillmentFlow.steps',
        ]);

        $flow = $this->flow->read($fulfillment);
        $payment = $fulfillment->order->latestPayment;

        return new OrderFacts(
            state: $this->state($fulfillment, $now),
            escrow: $this->escrow($fulfillment),
            flowId: $flow->flow?->id,
            flowName: $flow->flow instanceof FulfillmentFlow ? $flow->flow->name : DefaultFlow::NAME,
            steps: $flow->steps,
            progress: $flow->progress,
            completed: $this->completions($fulfillment, $flow->steps),
            cardLastFour: $payment?->card_last_four,
            paymentStatus: $payment?->status->label(),
        );
    }

    /**
     * Where the money for this parcel stands: the last movement its ledger
     * entries recorded, and null while the order behind it went unpaid.
     */
    public function escrow(Fulfillment $fulfillment): ?LedgerEntryType
    {
        return $this->latestMovement($fulfillment)?->type;
    }

    /**
     * The sentence under the buyer's name, built from the parcel's own
     * facts and the shell's clock.
     */
    public function state(Fulfillment $fulfillment, DateTimeImmutable $now): ParcelState
    {
        $step = $this->latestCompletion($fulfillment);
        $movement = $this->latestMovement($fulfillment);

        return new ParcelState(
            status: $fulfillment->status,
            placedAt: $fulfillment->order->placed_at->toDateTimeImmutable(),
            now: $now,
            lastStepLabel: $step?->stepLabel(),
            lastStepAt: $step?->occurred_at->toDateTimeImmutable(),
            carrier: $fulfillment->carrier,
            shippedAt: $fulfillment->shipped_at?->toDateTimeImmutable(),
            settledAt: $this->settledAt($fulfillment),
            settledAmount: $this->settledAmount($fulfillment, $movement),
            escrow: $movement?->type,
        );
    }

    /**
     * One entry per step the log says is behind this parcel, keyed by the
     * step it completed. A completion naming a step the seller has since
     * removed keeps its own words in the log and leaves no entry here,
     * which is the same reading {@see \App\Domain\Fulfillment\FulfillmentProgress} takes.
     *
     * @param  list<FlowStep>  $steps
     * @return array<string, CompletedStep>
     */
    public function completions(Fulfillment $fulfillment, array $steps): array
    {
        $byId = [];

        foreach ($steps as $step) {
            $byId[$step->id] = $step;
        }

        $completed = [];

        foreach ($this->stepCompletions($fulfillment) as $event) {
            $step = $byId[$event->fulfillment_flow_step_id] ?? null;

            if ($step instanceof FlowStep) {
                $completed[$step->id] = new CompletedStep(
                    step: $step,
                    actor: $this->actorOf($event, $fulfillment),
                    occurredAt: $event->occurred_at->toDateTimeImmutable(),
                );
            }
        }

        return $completed;
    }

    private function actorOf(FulfillmentEvent $event, Fulfillment $fulfillment): string
    {
        return match ($event->actor_type) {
            ActorType::Seller => ActorDisplay::nameOf($fulfillment->seller),
            ActorType::Customer => ActorDisplay::nameOf($fulfillment->customer),
            ActorType::Admin => ActorDisplay::SUPPORT_DESK,
        };
    }

    /**
     * What moved when the parcel came to rest. A refund names the sum the
     * buyer got back; every other movement is the seller's own, and the
     * ledger holds it net of the platform fee.
     */
    private function settledAmount(Fulfillment $fulfillment, ?LedgerEntry $movement): ?Money
    {
        $refunded = $fulfillment->refund?->amount();

        if ($refunded instanceof Money) {
            return $refunded;
        }

        return $movement instanceof LedgerEntry ? Money::fromCents(abs($movement->amount_cents)) : null;
    }

    /**
     * When this parcel came to rest: the day it was delivered, and the day
     * the money went back on one that was turned down.
     */
    private function settledAt(Fulfillment $fulfillment): ?DateTimeImmutable
    {
        return $fulfillment->status === FulfillmentStatus::Delivered
            ? $fulfillment->delivered_at?->toDateTimeImmutable()
            : $this->latestMovement($fulfillment)?->occurred_at->toDateTimeImmutable();
    }

    private function latestMovement(Fulfillment $fulfillment): ?LedgerEntry
    {
        return $fulfillment->ledgerEntries->sortBy(['occurred_at', 'id'])->last();
    }

    private function latestCompletion(Fulfillment $fulfillment): ?FulfillmentEvent
    {
        return $this->stepCompletions($fulfillment)->last();
    }

    /**
     * @return Collection<int, FulfillmentEvent>
     */
    private function stepCompletions(Fulfillment $fulfillment): Collection
    {
        return $fulfillment->fulfillmentEvents->where('kind', FulfillmentEventKind::StepCompleted);
    }
}
