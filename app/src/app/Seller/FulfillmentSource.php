<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Auth\ActorType;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Seller\ActivityKind;
use App\Domain\Seller\FeedEvent;
use App\Domain\Seller\FeedIcon;
use App\Models\Fulfillment;
use App\Models\FulfillmentEvent;
use App\View\ActorDisplay;

/**
 * The shipping half of the feed, read from `fulfillment_events` — every step
 * the seller completed and every transition that moved the parcel. A label
 * printed at 09:12 and a parcel marked shipped at 14:30 are two rows with
 * their own times.
 *
 * The refund a decline issues is the order source's row: it carries the
 * amount and the seller's reason, which the log does not, so the shipping row
 * says only that the parcel was turned down.
 */
final readonly class FulfillmentSource implements ActivityFeedSource
{
    /**
     * @return list<FeedEvent>
     */
    public function events(FeedScope $scope): array
    {
        if ($scope->fulfillmentIds === []) {
            return [];
        }

        $events = [];

        foreach ($this->fulfillments($scope) as $fulfillment) {
            foreach ($fulfillment->fulfillmentEvents as $event) {
                if ($event->kind === FulfillmentEventKind::Refunded) {
                    continue;
                }

                $events[] = $this->toFeedEvent($event, $fulfillment, $scope);
            }
        }

        return $events;
    }

    /**
     * @return list<Fulfillment>
     */
    private function fulfillments(FeedScope $scope): array
    {
        return array_values(Fulfillment::query()
            ->whereIn('id', $scope->fulfillmentIds)
            ->with('fulfillmentEvents')
            ->get()
            ->all());
    }

    private function toFeedEvent(FulfillmentEvent $event, Fulfillment $fulfillment, FeedScope $scope): FeedEvent
    {
        return new FeedEvent(
            occurredAt: $event->occurred_at->toDateTimeImmutable(),
            kind: ActivityKind::Shipping,
            icon: $this->iconOf($event),
            actor: $this->actorOf($event, $scope),
            text: $this->textOf($event, $fulfillment),
            link: route('seller.orders.show', $fulfillment->id),
        );
    }

    private function iconOf(FulfillmentEvent $event): FeedIcon
    {
        return match (true) {
            $event->carrier !== null => FeedIcon::Printer,
            $event->kind === FulfillmentEventKind::Shipped => FeedIcon::Truck,
            $event->kind === FulfillmentEventKind::Declined => FeedIcon::Undo,
            default => FeedIcon::Check,
        };
    }

    private function actorOf(FulfillmentEvent $event, FeedScope $scope): string
    {
        return match ($event->actor_type) {
            ActorType::Seller => 'You',
            ActorType::Customer => $scope->customerName,
            ActorType::Admin => ActorDisplay::SUPPORT_DESK,
        };
    }

    private function textOf(FulfillmentEvent $event, Fulfillment $fulfillment): string
    {
        return match ($event->kind) {
            FulfillmentEventKind::StepCompleted => $this->stepText($event),
            FulfillmentEventKind::Shipped => 'marked it shipped with '.($fulfillment->carrier ?? 'the carrier'),
            FulfillmentEventKind::Delivered => 'confirmed delivery',
            default => 'declined the order',
        };
    }

    private function stepText(FulfillmentEvent $event): string
    {
        return $event->carrier === null
            ? 'completed '.$event->stepLabel()
            : "printed the {$event->carrier} label · ".($event->tracking_number ?? '');
    }
}
