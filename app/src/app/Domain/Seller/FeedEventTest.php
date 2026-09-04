<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

it('carries every field it was built with', function (): void {
    $at = new DateTimeImmutable('2026-08-20 09:00:00');

    $event = new FeedEvent(
        occurredAt: $at,
        kind: ActivityKind::Messages,
        icon: FeedIcon::Chat,
        actor: 'Luna Lovegood',
        text: 'wrote in “Nine Owls”',
        quote: 'Does it come framed?',
        link: '/seller/messages/cnv_01J00000000000000000000ABC',
    );

    expect($event->occurredAt)->toBe($at)
        ->and($event->kind)->toBe(ActivityKind::Messages)
        ->and($event->icon)->toBe(FeedIcon::Chat)
        ->and($event->actor)->toBe('Luna Lovegood')
        ->and($event->text)->toBe('wrote in “Nine Owls”')
        ->and($event->quote)->toBe('Does it come framed?')
        ->and($event->link)->toBe('/seller/messages/cnv_01J00000000000000000000ABC');
});

it('defaults quote and link to null', function (): void {
    $event = new FeedEvent(
        occurredAt: new DateTimeImmutable('2026-08-20 09:00:00'),
        kind: ActivityKind::Browse,
        icon: FeedIcon::Eye,
        actor: 'Harry Potter',
        text: 'viewed Nine Owls',
    );

    expect($event->quote)->toBeNull()
        ->and($event->link)->toBeNull();
});

it('isOf is true for its own kind and false for any other', function (): void {
    $event = new FeedEvent(
        occurredAt: new DateTimeImmutable('2026-08-20 09:00:00'),
        kind: ActivityKind::Order,
        icon: FeedIcon::Bag,
        actor: 'Harry Potter',
        text: 'placed order ord_1',
    );

    expect($event->isOf(ActivityKind::Order))->toBeTrue()
        ->and($event->isOf(ActivityKind::Shipping))->toBeFalse();
});
