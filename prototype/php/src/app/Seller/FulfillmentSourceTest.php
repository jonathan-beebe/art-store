<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Fulfillment\DeclineFulfillment;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Seller\ActivityKind;
use App\Domain\Seller\FeedEvent;
use App\Domain\Seller\FeedIcon;
use App\Models\Customer;
use App\Models\FulfillmentEvent;

it('turns a completed label step into a printed-label row carrying the carrier and tracking number', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $fulfillment = $this->paidFulfillmentFor($seller);

    FulfillmentEvent::factory()->on($fulfillment)->create([
        'kind' => FulfillmentEventKind::StepCompleted,
        'step_label' => 'Label printed',
        'carrier' => 'Owl Post',
        'tracking_number' => 'OP 4471 2290 88 GB',
        'occurred_at' => $this->moment('2026-08-21 09:00:00'),
    ]);

    $events = (new FulfillmentSource)->events(FeedScope::forFulfillment($fulfillment));

    expect($events)->toHaveCount(1)
        ->and($events[0]->text)->toBe('printed the Owl Post label · OP 4471 2290 88 GB')
        ->and($events[0]->icon)->toBe(FeedIcon::Printer)
        ->and($events[0]->kind)->toBe(ActivityKind::Shipping);
});

it('turns a completed plain step into a "completed <label>" row', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $fulfillment = $this->paidFulfillmentFor($seller);

    FulfillmentEvent::factory()->on($fulfillment)->create([
        'kind' => FulfillmentEventKind::StepCompleted,
        'step_label' => 'Packed',
        'carrier' => null,
        'tracking_number' => null,
        'occurred_at' => $this->moment('2026-08-21 09:00:00'),
    ]);

    $events = (new FulfillmentSource)->events(FeedScope::forFulfillment($fulfillment));

    expect($events)->toHaveCount(1)
        ->and($events[0]->text)->toBe('completed Packed')
        ->and($events[0]->kind)->toBe(ActivityKind::Shipping);
});

it('gives shipped and delivered their own rows, with their own times and actors', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $fulfillment = $this->deliveredFulfillmentFor(
        $seller,
        $customer,
        carrier: 'Owl Post',
        trackingNumber: 'OP 4471 2290 88 GB',
        shippedAt: $this->moment('2026-08-21 10:00:00'),
        deliveredAt: $this->moment('2026-08-23 10:00:00'),
    );

    $events = (new FulfillmentSource)->events(FeedScope::forFulfillment($fulfillment));

    $shipped = array_values(array_filter($events, fn (FeedEvent $event): bool => $event->text === 'marked it shipped with Owl Post'));
    $delivered = array_values(array_filter($events, fn (FeedEvent $event): bool => $event->text === 'confirmed delivery'));

    expect($shipped)->toHaveCount(1)
        ->and($shipped[0]->actor)->toBe('You')
        ->and($shipped[0]->occurredAt)->toEqual($this->moment('2026-08-21 10:00:00'));

    expect($delivered)->toHaveCount(1)
        ->and($delivered[0]->actor)->toBe('Harry Potter')
        ->and($delivered[0]->occurredAt)->toEqual($this->moment('2026-08-23 10:00:00'));
});

it('a declined fulfillment\'s row carries the refund reason as its quote', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $fulfillment = $this->paidFulfillmentFor($seller);
    app(DeclineFulfillment::class)($fulfillment, 'The kiln cracked the glaze.', $this->moment('2026-08-21 09:00:00'));

    $events = (new FulfillmentSource)->events(FeedScope::forFulfillment($fulfillment->refresh()));
    $declined = array_values(array_filter($events, fn (FeedEvent $event): bool => $event->text === 'declined the order and refunded '.$fulfillment->subtotal()->format()));

    expect($declined)->toHaveCount(1)
        ->and($declined[0]->quote)->toBe('The kiln cracked the glaze.')
        ->and($declined[0]->kind)->toBe(ActivityKind::Shipping);
});

it('produces no row for a refunded event — the order source owns that story', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $fulfillment = $this->paidFulfillmentFor($seller);

    FulfillmentEvent::factory()->on($fulfillment)->create([
        'kind' => FulfillmentEventKind::Refunded,
        'occurred_at' => $this->moment('2026-08-21 09:00:00'),
    ]);

    expect((new FulfillmentSource)->events(FeedScope::forFulfillment($fulfillment)))->toBe([]);
});

it('every row is ActivityKind::Shipping', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Ginny Weasley']);
    $fulfillment = $this->deliveredFulfillmentFor($seller, $customer);

    FulfillmentEvent::factory()->on($fulfillment)->create([
        'kind' => FulfillmentEventKind::StepCompleted,
        'step_label' => 'Packed',
        'occurred_at' => $this->moment('2026-08-20 12:00:00'),
    ]);

    $events = (new FulfillmentSource)->events(FeedScope::forFulfillment($fulfillment));

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        expect($event->kind)->toBe(ActivityKind::Shipping);
    }
});
