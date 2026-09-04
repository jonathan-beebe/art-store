<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Fulfillment\AppendFulfillmentEvent;
use App\Actions\Fulfillment\SaveFulfillmentFlow;
use App\Domain\Auth\ActorType;
use App\Domain\Fulfillment\DefaultFlow;
use App\Domain\Fulfillment\FlowStep;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Fulfillment\NewFulfillmentEvent;
use App\Models\Fulfillment;
use App\Models\FulfillmentFlow;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Every seller gets the default flow, and every parcel that already left the
 * studio or reached the buyer gets the events behind it — the label step,
 * the ship, and the delivery — so the orders lanes and the activity feed
 * read from the first page load.
 */
class FulfillmentFlowSeeder extends Seeder
{
    public function run(): void
    {
        $saveFlow = app(SaveFulfillmentFlow::class);
        $appendEvent = app(AppendFulfillmentEvent::class);

        foreach (Seller::query()->get() as $seller) {
            $flow = $saveFlow($seller, DefaultFlow::NAME, DefaultFlow::drafts());

            $this->recordDepartureHistory($seller, $flow, $appendEvent);
        }
    }

    private function recordDepartureHistory(Seller $seller, FulfillmentFlow $flow, AppendFulfillmentEvent $appendEvent): void
    {
        $labelStep = $this->labelStep($flow);

        foreach ($this->departedFulfillments($seller) as $fulfillment) {
            $shippedAt = $fulfillment->shipped_at?->toDateTimeImmutable();

            if ($shippedAt === null) {
                continue;
            }

            if ($labelStep instanceof FlowStep) {
                $appendEvent($fulfillment, NewFulfillmentEvent::stepCompleted(
                    step: $labelStep,
                    actorType: ActorType::Seller,
                    actorId: $seller->id,
                    occurredAt: $shippedAt->modify('-4 hours'),
                    carrier: $fulfillment->carrier ?? 'Owl Post',
                    trackingNumber: $fulfillment->tracking_number ?? mb_substr($fulfillment->id, -8),
                ));
            }

            $appendEvent($fulfillment, NewFulfillmentEvent::transition(
                kind: FulfillmentEventKind::Shipped,
                actorType: ActorType::Seller,
                actorId: $seller->id,
                occurredAt: $shippedAt,
            ));

            $deliveredAt = $fulfillment->delivered_at?->toDateTimeImmutable();

            if ($deliveredAt === null) {
                continue;
            }

            $appendEvent($fulfillment, NewFulfillmentEvent::transition(
                kind: FulfillmentEventKind::Delivered,
                actorType: ActorType::Customer,
                actorId: $fulfillment->customer_id,
                occurredAt: $deliveredAt,
            ));
        }
    }

    private function labelStep(FulfillmentFlow $flow): ?FlowStep
    {
        foreach ($flow->flowSteps() as $step) {
            if ($step->printsLabel()) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Every parcel that shipped, whatever it settled into afterward — a
     * delivered one, and one an admin refunded after it shipped or after it
     * delivered.
     *
     * @return Collection<int, Fulfillment>
     */
    private function departedFulfillments(Seller $seller): Collection
    {
        return $seller->fulfillments()->whereNotNull('shipped_at')->get();
    }
}
