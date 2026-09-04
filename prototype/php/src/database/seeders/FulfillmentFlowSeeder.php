<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Fulfillment\AppendFulfillmentEvent;
use App\Actions\Fulfillment\SaveFulfillmentFlow;
use App\Domain\Auth\ActorType;
use App\Domain\Fulfillment\DefaultFlow;
use App\Domain\Fulfillment\FlowStep;
use App\Domain\Fulfillment\NewFulfillmentEvent;
use App\Models\Fulfillment;
use App\Models\FulfillmentFlow;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Every seller gets the default flow, and every parcel that already left the
 * studio gets the label step behind it — so the orders lanes and the activity
 * feed read from the first page load.
 */
class FulfillmentFlowSeeder extends Seeder
{
    private const string CARRIER = 'Owl Post';

    public function run(): void
    {
        $saveFlow = app(SaveFulfillmentFlow::class);
        $appendEvent = app(AppendFulfillmentEvent::class);

        foreach (Seller::query()->get() as $seller) {
            $flow = $saveFlow($seller, DefaultFlow::NAME, DefaultFlow::drafts());

            $this->recordLabelsAlreadyPrinted($seller, $flow, $appendEvent);
        }
    }

    private function recordLabelsAlreadyPrinted(Seller $seller, FulfillmentFlow $flow, AppendFulfillmentEvent $appendEvent): void
    {
        $labelStep = $this->labelStep($flow);

        if (! $labelStep instanceof FlowStep) {
            return;
        }

        foreach ($this->departedFulfillments($seller) as $fulfillment) {
            $shippedAt = $fulfillment->shipped_at?->toDateTimeImmutable();

            if ($shippedAt === null) {
                continue;
            }

            $appendEvent($fulfillment, NewFulfillmentEvent::stepCompleted(
                step: $labelStep,
                actorType: ActorType::Seller,
                actorId: $seller->id,
                occurredAt: $shippedAt->modify('-4 hours'),
                carrier: self::CARRIER,
                trackingNumber: 'OP '.mb_substr($fulfillment->id, -8),
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
     * @return Collection<int, Fulfillment>
     */
    private function departedFulfillments(Seller $seller): Collection
    {
        return $seller->fulfillments()->whereNotNull('shipped_at')->get();
    }
}
