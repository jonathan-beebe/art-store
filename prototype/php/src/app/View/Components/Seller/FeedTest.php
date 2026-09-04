<?php

declare(strict_types=1);

namespace App\View\Components\Seller;

use App\Domain\Seller\ActivityFeed;
use App\Domain\Seller\ActivityKind;
use App\Domain\Seller\FeedEvent;
use App\Domain\Seller\FeedIcon;
use DateTimeImmutable;
use Illuminate\Support\Facades\Blade;

function sellerFeedHtml(ActivityFeed $feed): string
{
    return Blade::render('<x-seller.feed :feed="$feed" />', ['feed' => $feed]);
}

function sellerFeedOf(FeedEvent ...$events): ActivityFeed
{
    return ActivityFeed::merge(array_values($events));
}

function sellerFeedMoment(string $when): DateTimeImmutable
{
    return new DateTimeImmutable($when);
}

it('renders one row per event in the Tailwind Plus feed shape', function (): void {
    $html = sellerFeedHtml(sellerFeedOf(
        new FeedEvent(sellerFeedMoment('2026-09-02T09:12:00+00:00'), ActivityKind::Shipping, FeedIcon::Printer, 'You', 'printed an Owl Post label'),
        new FeedEvent(sellerFeedMoment('2026-09-01T14:30:00+00:00'), ActivityKind::Browse, FeedIcon::Eye, 'Harry Potter', 'viewed Nine Owls'),
    ));

    expect($html)->toContain('<ul role="list"')
        ->and(substr_count($html, 'data-feed-row'))->toBe(2)
        ->and($html)->toContain('printed an Owl Post label')
        ->toContain('viewed Nine Owls');
});

it('draws the rail on every row but the last', function (): void {
    $one = sellerFeedHtml(sellerFeedOf(
        new FeedEvent(sellerFeedMoment('2026-09-02T09:12:00+00:00'), ActivityKind::Browse, FeedIcon::Eye, 'Harry Potter', 'viewed Nine Owls'),
    ));

    $three = sellerFeedHtml(sellerFeedOf(
        new FeedEvent(sellerFeedMoment('2026-09-03T09:12:00+00:00'), ActivityKind::Browse, FeedIcon::Eye, 'Harry Potter', 'viewed Nine Owls'),
        new FeedEvent(sellerFeedMoment('2026-09-02T09:12:00+00:00'), ActivityKind::Browse, FeedIcon::Heart, 'Harry Potter', 'favorited Nine Owls'),
        new FeedEvent(sellerFeedMoment('2026-09-01T09:12:00+00:00'), ActivityKind::Browse, FeedIcon::Cart, 'Harry Potter', 'added Nine Owls to their cart'),
    ));

    expect(substr_count($one, 'data-rail'))->toBe(0)
        ->and(substr_count($three, 'data-rail'))->toBe(2);
});

it('accents an order row and leaves every other kind muted', function (): void {
    $html = sellerFeedHtml(sellerFeedOf(
        new FeedEvent(sellerFeedMoment('2026-09-02T09:12:00+00:00'), ActivityKind::Order, FeedIcon::Bag, 'Harry Potter', 'placed order ord_1'),
        new FeedEvent(sellerFeedMoment('2026-09-01T09:12:00+00:00'), ActivityKind::Messages, FeedIcon::Chat, 'You', 'replied'),
    ));

    expect($html)->toContain('data-accent="true"')
        ->toContain('data-accent="false"');
});

it('renders the actor in the strong voice and draws the icon path the event carries', function (): void {
    $html = sellerFeedHtml(sellerFeedOf(
        new FeedEvent(sellerFeedMoment('2026-09-02T09:12:00+00:00'), ActivityKind::Shipping, FeedIcon::Truck, 'You', 'marked it shipped'),
    ));

    expect($html)->toContain('data-feed-actor')
        ->toContain('>You</span>')
        ->toContain('d="'.FeedIcon::Truck->path().'"');
});

it('quotes the words a message or a decline carries', function (): void {
    $html = sellerFeedHtml(sellerFeedOf(
        new FeedEvent(
            sellerFeedMoment('2026-09-02T09:12:00+00:00'),
            ActivityKind::Messages,
            FeedIcon::Chat,
            'Harry Potter',
            'wrote in a thread',
            quote: 'Do the owls come framed?',
        ),
    ));

    expect($html)->toContain('data-feed-quote')
        ->toContain('Do the owls come framed?');
});

it('links a row that names somewhere to go', function (): void {
    $html = sellerFeedHtml(sellerFeedOf(
        new FeedEvent(
            sellerFeedMoment('2026-09-02T09:12:00+00:00'),
            ActivityKind::Browse,
            FeedIcon::Eye,
            'Harry Potter',
            'viewed Nine Owls',
            link: '/seller/listings/lst_01J5X3M9A2K8YB7Q4R6T1V0WZE',
        ),
    ));

    expect($html)->toContain('href="/seller/listings/lst_01J5X3M9A2K8YB7Q4R6T1V0WZE"');
});

it('stamps each row with the instant it happened', function (): void {
    $html = sellerFeedHtml(sellerFeedOf(
        new FeedEvent(sellerFeedMoment('2026-09-02T09:12:00+00:00'), ActivityKind::Browse, FeedIcon::Eye, 'Harry Potter', 'viewed Nine Owls'),
    ));

    expect($html)->toContain('datetime="2026-09-02T09:12:00+00:00"')
        ->toContain('Sep 2 · 09:12');
});

it('says so when nothing has happened', function (): void {
    $html = sellerFeedHtml(ActivityFeed::merge());

    expect($html)->toContain('Nothing has happened here yet.');
    expect($html)->not->toContain('data-feed-row');
});

it('takes the empty sentence a page gives it', function (): void {
    $html = Blade::render(
        '<x-seller.feed :feed="$feed" empty="No activity on this order." />',
        ['feed' => ActivityFeed::merge()],
    );

    expect($html)->toContain('No activity on this order.');
});
